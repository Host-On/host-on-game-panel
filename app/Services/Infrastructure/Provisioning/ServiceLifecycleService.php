<?php

namespace Pterodactyl\Services\Infrastructure\Provisioning;

use Pterodactyl\Models\GameService;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Services\Nodes\NodeDeletionService;
use Pterodactyl\Services\Infrastructure\IpPoolService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Infrastructure\GameLicenseService;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;
use Pterodactyl\Services\Infrastructure\InfrastructureProviderManager;

/**
 * Handles the lifecycle operations (suspend, unsuspend, resize, terminate)
 * for a managed game service, coordinating the Pterodactyl resources with the
 * underlying compute instance.
 */
class ServiceLifecycleService
{
    public function __construct(
        protected InfrastructureProviderManager $providerManager,
        protected ServerDeletionService $serverDeletion,
        protected NodeDeletionService $nodeDeletion,
        protected IpPoolService $ipPool,
        protected GameLicenseService $gameLicenses,
        protected \Pterodactyl\Services\Servers\SuspensionService $suspension,
        protected \Pterodactyl\Services\Infrastructure\InfrastructureAuditService $audit,
    ) {
    }

    public function suspend(GameService $service): void
    {
        if ($service->server) {
            $instance = $service->computeInstance;
            $isDemo = $instance?->cluster?->type === InfrastructureCluster::TYPE_FAKE;

            if ($isDemo) {
                // Demo clusters have no live daemon — set the status directly.
                $service->server->update(['status' => \Pterodactyl\Models\Server::STATUS_SUSPENDED]);
            } else {
                $this->suspension->toggle($service->server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_SUSPEND);
            }
        }

        if ($service->computeInstance) {
            $this->provider($service->computeInstance)->stopInstance(
                $service->computeInstance->vmid,
                $service->computeInstance->host?->external_id ?? $service->computeInstance->host?->name
            );
            $service->computeInstance->update(['status' => ComputeInstance::STATUS_SUSPENDED]);
        }

        $service->update(['status' => GameService::STATUS_SUSPENDED]);

        $this->audit->record('service.suspend', [
            'server_id' => $service->server_id,
            'compute_instance_id' => $service->compute_instance_id,
            'vmid' => $service->computeInstance?->vmid,
            'target_type' => 'game_service',
            'target_id' => (string) $service->id,
        ]);
    }

    public function unsuspend(GameService $service): void
    {
        if ($service->computeInstance) {
            $this->provider($service->computeInstance)->startInstance(
                $service->computeInstance->vmid,
                $service->computeInstance->host?->external_id ?? $service->computeInstance->host?->name
            );
            $service->computeInstance->update(['status' => ComputeInstance::STATUS_RUNNING]);
        }

        if ($service->server) {
            $instance = $service->computeInstance;
            $isDemo = $instance?->cluster?->type === InfrastructureCluster::TYPE_FAKE;

            if ($isDemo) {
                $service->server->update(['status' => null]);
            } else {
                $this->suspension->toggle($service->server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_UNSUSPEND);
            }
        }

        $service->update(['status' => GameService::STATUS_ACTIVE]);

        $this->audit->record('service.unsuspend', [
            'server_id' => $service->server_id,
            'compute_instance_id' => $service->compute_instance_id,
            'vmid' => $service->computeInstance?->vmid,
            'target_type' => 'game_service',
            'target_id' => (string) $service->id,
        ]);
    }

    public function terminate(GameService $service): void
    {
        $service->update(['status' => GameService::STATUS_TERMINATING]);

        if ($service->server) {
            $this->serverDeletion->withForce()->handle($service->server);
        }

        $node = $service->computeInstance?->node;
        $instance = $service->computeInstance;

        if ($instance) {
            // Only ever delete the VMID recorded on OUR compute instance —
            // this ID was created by provisioning and stored in our DB.
            // If no VMID was ever recorded, the VM is not touched.
            if (!empty($instance->vmid)) {
                $this->provider($instance)->deleteInstance(
                    $instance->vmid,
                    $instance->host?->external_id ?? $instance->host?->name
                );
            }

            // Release the dedicated public IP back into the pool.
            $this->ipPool->releaseForInstance($instance);

            $instance->update([
                'status' => ComputeInstance::STATUS_TERMINATED,
                'terminated_at' => now(),
                'wings_node_id' => null,
            ]);
        }

        // Release any allocated commercial game license back into its pool.
        $this->gameLicenses->releaseForService($service);

        $this->audit->record('service.terminate', [
            'server_id' => $service->server_id,
            'compute_instance_id' => $instance?->id,
            'vmid' => $instance?->vmid,
            'target_type' => 'game_service',
            'target_id' => (string) $service->id,
        ]);

        if ($node) {
            try {
                $this->nodeDeletion->handle($node);
            } catch (\Throwable $exception) {
                // Node deletion may fail if servers still reference it; flag for review
                // rather than leaving the service in an undefined state.
                report($exception);
            }
        }

        $service->update(['status' => GameService::STATUS_TERMINATED]);
    }

    public function resize(GameService $service, int $cpu, int $memory, int $disk): void
    {
        $instance = $service->computeInstance;

        if ($instance) {
            $this->provider($instance)->resizeInstance(
                $instance->vmid,
                $instance->host?->external_id ?? $instance->host?->name,
                $cpu,
                $memory,
                $disk
            );

            $instance->update(['cpu' => $cpu, 'memory' => $memory, 'disk' => $disk]);

            if ($instance->node) {
                $instance->node->update(['memory' => $memory, 'disk' => $disk]);
            }
        }

        if ($service->server) {
            $service->server->update([
                'cpu' => $cpu,
                'memory' => max(0, $memory - ($service->profile?->system_reserve ?? 0)),
                'disk' => $disk,
            ]);
        }

        $this->audit->record('service.resize', [
            'compute_instance_id' => $instance?->id,
            'vmid' => $instance?->vmid,
            'target_type' => 'game_service',
            'target_id' => (string) $service->id,
            'before' => ['cpu' => $service->computeInstance?->getOriginal('cpu'), 'memory' => $service->computeInstance?->getOriginal('memory'), 'disk' => $service->computeInstance?->getOriginal('disk')],
            'after' => ['cpu' => $cpu, 'memory' => $memory, 'disk' => $disk],
        ]);
    }

    protected function provider(ComputeInstance $instance): \Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface
    {
        $cluster = $instance->cluster;

        if (!$cluster) {
            throw new InfrastructureException('This compute instance is not associated with an infrastructure cluster.');
        }

        return $this->providerManager->for($cluster);
    }
}
