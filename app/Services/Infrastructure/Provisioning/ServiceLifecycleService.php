<?php

namespace Pterodactyl\Services\Infrastructure\Provisioning;

use Pterodactyl\Models\GameService;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\InfrastructureProvider;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Nodes\NodeDeletionService;
use Pterodactyl\Services\Infrastructure\IpPoolService;
use Pterodactyl\Services\Infrastructure\GameLicenseService;
use Pterodactyl\Services\Infrastructure\InfrastructureProviderManager;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;

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
    ) {
    }

    public function suspend(GameService $service): void
    {
        if ($service->server) {
            $service->server->update(['status' => \Pterodactyl\Models\Server::STATUS_SUSPENDED]);
        }

        if ($service->computeInstance) {
            $this->provider($service->computeInstance)->stopInstance(
                $service->computeInstance->vmid,
                $service->computeInstance->host?->external_id ?? $service->computeInstance->host?->name
            );
            $service->computeInstance->update(['status' => ComputeInstance::STATUS_SUSPENDED]);
        }

        $service->update(['status' => GameService::STATUS_SUSPENDED]);
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
            $service->server->update(['status' => null]);
        }

        $service->update(['status' => GameService::STATUS_ACTIVE]);
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
            $this->provider($instance)->deleteInstance(
                $instance->vmid,
                $instance->host?->external_id ?? $instance->host?->name
            );

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
    }

    protected function provider(ComputeInstance $instance): \Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface
    {
        $provider = $instance->provider;

        if (!$provider) {
            throw new InfrastructureException('This compute instance is not associated with an infrastructure provider.');
        }

        return $this->providerManager->for($provider);
    }
}
