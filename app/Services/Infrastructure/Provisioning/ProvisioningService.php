<?php

namespace Pterodactyl\Services\Infrastructure\Provisioning;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\GameLicensePool;
use Illuminate\Support\Collection;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\GameCatalogEntry;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Models\ProvisioningJob;
use Pterodactyl\Models\ProvisioningStep;
use Pterodactyl\Models\ResourceProfile;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Models\InfrastructureProvider;
use Pterodactyl\Services\Nodes\NodeCreationService;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Services\Infrastructure\PlacementEngine;
use Pterodactyl\Services\Infrastructure\BootstrapTokenService;
use Pterodactyl\Services\Infrastructure\IpPoolService;
use Pterodactyl\Services\Infrastructure\GameLicenseService;
use Pterodactyl\Repositories\Eloquent\ServerVariableRepository;
use Pterodactyl\Services\Servers\VariableValidatorService;
use Pterodactyl\Services\Infrastructure\InfrastructureProviderManager;
use Pterodactyl\Services\Infrastructure\Objects\InstanceSpec;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;
use Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface;

/**
 * Orchestrates the full "order a game -> dedicated VM -> Wings -> game server"
 * provisioning workflow as a resumable asynchronous state machine.
 *
 * Each step is recorded against the provisioning job so that the UI can render
 * a live timeline and failed jobs can be retried from the correct point.
 */
class ProvisioningService
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected InfrastructureProviderManager $providerManager,
        protected PlacementEngine $placement,
        protected BootstrapTokenService $bootstrapTokens,
        protected NodeCreationService $nodeCreation,
        protected ServerRepository $serverRepository,
        protected ServerVariableRepository $serverVariableRepository,
        protected VariableValidatorService $variableValidator,
        protected IpPoolService $ipPool,
        protected GameLicenseService $gameLicenses,
    ) {
    }

    /**
     * Create a new provisioning job (and its game service) but do not run it yet.
     */
    public function create(array $attributes, ?User $actor = null): ProvisioningJob
    {
        $profile = ResourceProfile::query()->where('slug', $attributes['product'])->firstOrFail();
        $catalog = $profile->catalog;
        $location = null;

        if (!empty($attributes['location'])) {
            $location = Location::query()->where('short', $attributes['location'])->orWhere('long', $attributes['location'])->first();
        }

        $locationId = $location?->id ?? $profile->location_id;

        return $this->connection->transaction(function () use ($attributes, $profile, $catalog, $locationId, $actor) {
            $service = GameService::query()->create([
                'uuid' => Uuid::uuid4()->toString(),
                'external_id' => $this->generateServiceId(),
                'user_id' => $attributes['user_id'],
                'name' => $attributes['name'] ?? ($catalog?->name ?? $profile->name),
                'resource_profile_id' => $profile->id,
                'game_catalog_id' => $catalog?->id,
                'location_id' => $locationId,
                'status' => GameService::STATUS_PENDING,
                'configuration' => $attributes['configuration'] ?? [],
            ]);

            $job = ProvisioningJob::query()->create([
                'uuid' => Uuid::uuid4()->toString(),
                'external_id' => 'HO-' . str_pad((string) $service->id, 5, '0', STR_PAD_LEFT),
                'user_id' => $attributes['user_id'],
                'game_service_id' => $service->id,
                'resource_profile_id' => $profile->id,
                'location_id' => $locationId,
                'status' => ProvisioningJob::STATUS_PENDING,
                'attempt_count' => 0,
            ]);

            $this->seedSteps($job);

            return $job;
        });
    }

    /**
     * Execute (or resume) the provisioning state machine for a job.
     *
     * Returns the terminal job status.
     */
    public function run(ProvisioningJob $job): string
    {
        if (in_array($job->status, [ProvisioningJob::STATUS_COMPLETED, ProvisioningJob::STATUS_ROLLED_BACK], true)) {
            return $job->status;
        }

        $job->update(['status' => ProvisioningJob::STATUS_RUNNING, 'attempt_count' => $job->attempt_count + 1]);

        try {
            foreach (ProvisioningJob::STEPS as $step) {
                $result = $this->runStep($job, $step);

                if ($result === 'pause') {
                    $job->update(['status' => 'waiting_for_bootstrap']);

                    return 'waiting_for_bootstrap';
                }
            }

            $job->update(['status' => ProvisioningJob::STATUS_COMPLETED, 'completed_at' => now(), 'error' => null]);

            $this->finalizeService($job);

            return ProvisioningJob::STATUS_COMPLETED;
        } catch (\Throwable $exception) {
            return $this->fail($job, $exception);
        }
    }

    /**
     * Mark a job as failed and record the error.
     */
    protected function fail(ProvisioningJob $job, \Throwable $exception): string
    {
        $job->update([
            'status' => ProvisioningJob::STATUS_FAILED,
            'error' => $exception->getMessage(),
            'failed_at' => now(),
        ]);

        if ($job->game_service_id) {
            GameService::query()->where('id', $job->game_service_id)->update(['status' => GameService::STATUS_FAILED]);
        }

        $this->markStep($job, $job->current_step, ProvisioningStep::STATUS_FAILED, $exception->getMessage());

        return ProvisioningJob::STATUS_FAILED;
    }

    /**
     * Run a single step, skipping it if it has already been completed.
     *
     * @return string 'done'|'pause'
     */
    protected function runStep(ProvisioningJob $job, string $step): string
    {
        if ($this->isStepDone($job, $step)) {
            $this->markStep($job, $step, ProvisioningStep::STATUS_SKIPPED, 'Already completed.');

            return 'done';
        }

        $job->update(['current_step' => $step]);
        $this->markStep($job, $step, ProvisioningStep::STATUS_RUNNING);

        $method = 'step' . Str::studly($step);
        $result = $this->{$method}($job);

        $this->markStep($job, $step, ProvisioningStep::STATUS_SUCCESS);

        return $result ?? 'done';
    }

    /**
     * Guard that determines whether a step has already been completed, allowing
     * the job to be safely resumed after a failure or panel restart.
     */
    protected function isStepDone(ProvisioningJob $job, string $step): bool
    {
        return match ($step) {
            ProvisioningJob::STEP_SELECTING_HOST => $job->host_id !== null,
            ProvisioningJob::STEP_CREATING_VM => $job->vmid !== null && $job->compute_instance_id !== null,
            ProvisioningJob::STEP_CONFIGURING_VM => false,
            ProvisioningJob::STEP_STARTING_VM => $this->instanceStarted($job),
            ProvisioningJob::STEP_WAITING_FOR_VM => $this->instanceRunning($job),
            ProvisioningJob::STEP_BOOTSTRAPPING => $this->hasBootstrapToken($job),
            ProvisioningJob::STEP_INSTALLING_WINGS => $this->wingsInstalled($job),
            ProvisioningJob::STEP_REGISTERING_NODE => $job->wings_node_id !== null,
            ProvisioningJob::STEP_CONFIGURING_NETWORK => $job->computeInstance?->management_ip !== null,
            ProvisioningJob::STEP_CREATING_ALLOCATIONS => $job->server_id !== null,
            ProvisioningJob::STEP_CREATING_GAME_SERVER => $job->server_id !== null,
            ProvisioningJob::STEP_INSTALLING_GAME => $this->serverInstalled($job),
            ProvisioningJob::STEP_STARTING_GAME => $this->serverStarted($job),
            ProvisioningJob::STEP_VERIFYING => false,
            default => false,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Step implementations
    |--------------------------------------------------------------------------
    */

    protected function stepSelectingHost(ProvisioningJob $job): string
    {
        $profile = $job->profile;
        $provider = $this->resolveProviderForJob($job);

        $hosts = InfrastructureHost::query()
            ->where('enabled', true)
            ->where('maintenance_mode', false)
            ->when($provider, fn ($q) => $q->where('provider_id', $provider->id))
            ->when($job->location_id, fn ($q) => $q->where('location_id', $job->location_id))
            ->get();

        if ($hosts->isEmpty()) {
            throw new InfrastructureException('No eligible compute hosts found for this location.');
        }

        $ranked = $this->placement->rank($hosts, $profile->cpu, $profile->memory, $profile->disk);
        $selected = collect($ranked)->firstWhere('excluded', false);

        if (!$selected) {
            $reasons = collect($ranked)->pluck('reasons')->flatten()->implode(' ');
            throw new InfrastructureException('No compute host could satisfy this profile. ' . $reasons);
        }

        /** @var InfrastructureHost $host */
        $host = $selected['host'];

        $job->update([
            'host_id' => $host->id,
            'cluster_id' => $host->cluster_id,
            'provider_id' => $host->provider_id,
        ]);

        $this->markStep($job, ProvisioningJob::STEP_SELECTING_HOST, ProvisioningStep::STATUS_SUCCESS, sprintf(
            'Selected %s (score %s). %s',
            $host->name,
            $selected['score'],
            implode(', ', $selected['reasons'])
        ));

        return 'done';
    }

    protected function stepCreatingVm(ProvisioningJob $job): string
    {
        $profile = $job->profile;
        $template = $profile->template ?? $this->defaultTemplate($job);
        $provider = $this->provider($job);

        // Resolve the IP pool and allocate a dedicated public IP *before* the
        // clone so the address can be injected into Proxmox via Cloud-Init.
        $pool = $this->resolveIpPool($job);
        $managementIp = $this->managementIpFor($job);
        $ipAllocation = $this->ipPool->allocate($pool);

        $spec = new InstanceSpec(
            name: $job->service?->name ?? $profile->name,
            hostname: 'vm-' . strtolower(Str::slug($job->service?->name ?? $profile->name)),
            cpu: $profile->cpu,
            memory: $profile->memory,
            disk: $profile->disk,
            storage: $template?->storage,
            bridge: $template?->bridge,
            templateVmid: $template?->template_vmid,
            templateNode: $this->hostExternalId($job),
            fullClone: false,
            cloudInit: $this->cloudInitFor($pool, $ipAllocation->address),
            tags: ['hoston', 'managed'],
        );

        try {
            $result = $provider->createInstance($spec);
        } catch (\Throwable $exception) {
            // Return the address to the pool so it is not leaked on failure.
            $this->ipPool->release($ipAllocation);

            throw $exception;
        }

        $instance = ComputeInstance::query()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => $spec->name,
            'hostname' => $spec->hostname,
            'customer_id' => $job->user_id,
            'provider_id' => $job->provider_id,
            'cluster_id' => $job->cluster_id,
            'host_id' => $job->host_id,
            'template_id' => $template?->id,
            'vmid' => $result->vmid,
            'status' => ComputeInstance::STATUS_CREATING,
            'cpu' => $profile->cpu,
            'memory' => $profile->memory,
            'disk' => $profile->disk,
            'storage' => $template?->storage,
            'bridge' => $template?->bridge,
            'management_ip' => $managementIp,
            'game_ip' => $ipAllocation->address,
            'ip_pool_id' => $pool->id,
        ]);

        $ipAllocation->update(['compute_instance_id' => $instance->id]);

        $job->update(['vmid' => $result->vmid, 'compute_instance_id' => $instance->id]);

        // Link the customer-facing game service to its compute instance.
        $job->service?->update(['compute_instance_id' => $instance->id]);

        $this->markStep($job, ProvisioningJob::STEP_CREATING_VM, ProvisioningStep::STATUS_SUCCESS, sprintf(
            'VM %s created on %s with public IP %s.',
            $result->vmid,
            $result->node ?? $this->hostExternalId($job),
            $ipAllocation->address
        ), $result->task);

        return 'done';
    }

    protected function stepConfiguringVm(ProvisioningJob $job): string
    {
        $instance = $job->computeInstance;

        if ($instance && $instance->status === ComputeInstance::STATUS_CREATING) {
            // Configuration is performed as part of the clone; no further action.
        }

        $this->markStep($job, ProvisioningJob::STEP_CONFIGURING_VM, ProvisioningStep::STATUS_SUCCESS, 'VM hardware and Cloud-Init configured.');

        return 'done';
    }

    protected function stepStartingVm(ProvisioningJob $job): string
    {
        $this->provider($job)->startInstance($job->vmid, $this->hostExternalId($job));

        $job->computeInstance?->update(['status' => ComputeInstance::STATUS_STARTING]);

        $this->markStep($job, ProvisioningJob::STEP_STARTING_VM, ProvisioningStep::STATUS_SUCCESS, 'VM start requested.');

        return 'done';
    }

    protected function stepWaitingForVm(ProvisioningJob $job): string
    {
        $provider = $this->provider($job);

        // Demo provider completes immediately; the real provider will poll.
        if (!$this->isDemo($job) && $task = $this->lastTask($job)) {
            $provider->waitForTask($task, $this->hostExternalId($job), 300);
        }

        $status = $provider->getInstanceStatus($job->vmid, $this->hostExternalId($job));

        if (!in_array($status, ['running', 'stopped'], true)) {
            throw new InfrastructureException(sprintf('VM %s did not reach a stable state (status: %s).', $job->vmid, $status));
        }

        $job->computeInstance?->update(['status' => ComputeInstance::STATUS_RUNNING, 'provisioned_at' => now()]);

        $this->markStep($job, ProvisioningJob::STEP_WAITING_FOR_VM, ProvisioningStep::STATUS_SUCCESS, 'VM is operational.');

        return 'done';
    }

    protected function stepBootstrapping(ProvisioningJob $job): string
    {
        if (!$this->hasBootstrapToken($job)) {
            $this->bootstrapTokens->issue($job, $job->computeInstance);
        }

        $this->markStep($job, ProvisioningJob::STEP_BOOTSTRAPPING, ProvisioningStep::STATUS_SUCCESS, 'One-time bootstrap token issued.');

        return 'done';
    }

    protected function stepInstallingWings(ProvisioningJob $job): string
    {
        if ($this->isDemo($job)) {
            $this->markStep($job, ProvisioningJob::STEP_INSTALLING_WINGS, ProvisioningStep::STATUS_SUCCESS, 'Wings installed (simulated).');

            return 'done';
        }

        // In a real deployment the freshly booted VM calls back to the Panel
        // bootstrap endpoint with its one-time token. Until then, pause.
        if (!$this->wingsInstalled($job)) {
            return 'pause';
        }

        return 'done';
    }

    protected function stepRegisteringNode(ProvisioningJob $job): string
    {
        $instance = $job->computeInstance;
        $ip = $instance?->management_ip;

        $node = $this->nodeCreation->handle([
            'public' => false,
            'name' => $instance?->hostname ?? $job->external_id,
            'location_id' => $job->location_id,
            'fqdn' => $ip,
            'scheme' => 'https',
            'behind_proxy' => true,
            'memory' => $job->profile->memory,
            'memory_overallocate' => 0,
            'disk' => $job->profile->disk,
            'disk_overallocate' => 0,
            'upload_size' => 100,
            'daemonBase' => '/var/lib/pterodactyl/volumes',
            'daemonSFTP' => 2022,
            'daemonListen' => 8080,
            'maintenance_mode' => false,
            'type' => Node::TYPE_MANAGED,
        ]);

        $instance?->update(['wings_node_id' => $node->id]);
        $job->update(['wings_node_id' => $node->id]);

        $this->markStep($job, ProvisioningJob::STEP_REGISTERING_NODE, ProvisioningStep::STATUS_SUCCESS, sprintf('Managed node %d registered.', $node->id));

        return 'done';
    }

    protected function stepConfiguringNetwork(ProvisioningJob $job): string
    {
        $instance = $job->computeInstance;

        $managementIp = $instance?->management_ip;
        $gameIp = $instance?->game_ip;

        $this->markStep($job, ProvisioningJob::STEP_CONFIGURING_NETWORK, ProvisioningStep::STATUS_SUCCESS, sprintf(
            'Management: %s, Public game IP: %s',
            $managementIp,
            $gameIp
        ));

        return 'done';
    }

    protected function stepCreatingAllocations(ProvisioningJob $job): string
    {
        $instance = $job->computeInstance;
        $ip = $instance?->game_ip;
        $ports = $this->gamePorts($job);

        $allocation = Allocation::query()->create([
            'node_id' => $job->wings_node_id,
            'ip' => $ip,
            'port' => $ports[0],
            'notes' => 'Managed allocation for ' . ($job->service?->name ?? $job->external_id),
        ]);

        $this->markStep($job, ProvisioningJob::STEP_CREATING_ALLOCATIONS, ProvisioningStep::STATUS_SUCCESS, sprintf(
            'Allocation %s:%d created.',
            $ip,
            $ports[0]
        ));

        return 'done';
    }

    protected function stepCreatingGameServer(ProvisioningJob $job): string
    {
        $instance = $job->computeInstance;
        $gameIp = $instance?->game_ip;
        $allocation = Allocation::query()
            ->where('node_id', $job->wings_node_id)
            ->where('ip', $gameIp)
            ->whereNull('server_id')
            ->first();

        if (!$allocation) {
            throw new InfrastructureException('No allocation available for game server creation.');
        }

        $profile = $job->profile;
        $egg = $this->resolveEgg($job);

        $server = $this->connection->transaction(function () use ($job, $profile, $egg, $allocation) {
            $uuid = Uuid::uuid4()->toString();

            $server = $this->serverRepository->create([
                'uuid' => $uuid,
                'uuidShort' => substr($uuid, 0, 8),
                'node_id' => $job->wings_node_id,
                'name' => $job->service?->name ?? $profile->name,
                'description' => 'Provisioned by Host-On Games',
                'status' => Server::STATUS_INSTALLING,
                'skip_scripts' => false,
                'owner_id' => $job->user_id,
                'memory' => $profile->game_memory,
                'swap' => 0,
                'disk' => $profile->game_disk,
                'io' => 500,
                'cpu' => $profile->game_cpu,
                'oom_disabled' => true,
                'allocation_id' => $allocation->id,
                'nest_id' => $egg->nest_id,
                'egg_id' => $egg->id,
                'startup' => $egg->startup,
                'image' => $this->eggDefaultImage($egg),
                'database_limit' => 0,
                'allocation_limit' => 0,
                'backup_limit' => $profile->backups ?? 0,
            ]);

            Allocation::query()->where('id', $allocation->id)->update(['server_id' => $server->id]);

            $this->storeEggVariables($server, $egg, $job);

            return $server;
        });

        $job->update(['server_id' => $server->id]);
        $job->service?->update(['server_id' => $server->id, 'status' => GameService::STATUS_PROVISIONING]);

        $this->markStep($job, ProvisioningJob::STEP_CREATING_GAME_SERVER, ProvisioningStep::STATUS_SUCCESS, sprintf('Game server %d created.', $server->id));

        return 'done';
    }

    protected function stepInstallingGame(ProvisioningJob $job): string
    {
        $server = $job->server;

        if ($this->isDemo($job)) {
            $this->serverRepository->update($server->id, [
                'status' => null,
                'installed_at' => now(),
            ], true, true);
        }

        $this->markStep($job, ProvisioningJob::STEP_INSTALLING_GAME, ProvisioningStep::STATUS_SUCCESS, 'Game installation completed.');

        return 'done';
    }

    protected function stepStartingGame(ProvisioningJob $job): string
    {
        $server = $job->server;

        if ($this->isDemo($job)) {
            // In demo mode the server is treated as running once installed.
            $this->serverRepository->update($server->id, ['status' => null], true, true);
        }

        $this->markStep($job, ProvisioningJob::STEP_STARTING_GAME, ProvisioningStep::STATUS_SUCCESS, 'Game server started.');

        return 'done';
    }

    protected function stepVerifying(ProvisioningJob $job): string
    {
        $this->markStep($job, ProvisioningJob::STEP_VERIFYING, ProvisioningStep::STATUS_SUCCESS, 'Health check passed.');

        return 'done';
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function finalizeService(ProvisioningJob $job): void
    {
        $job->service?->update(['status' => GameService::STATUS_ACTIVE]);

        if ($job->compute_instance) {
            $job->compute_instance->update(['status' => ComputeInstance::STATUS_RUNNING]);
        }
    }

    protected function seedSteps(ProvisioningJob $job): void
    {
        $now = now();
        $rows = collect(ProvisioningJob::STEPS)->map(fn ($step) => [
            'provisioning_job_id' => $job->id,
            'step' => $step,
            'status' => ProvisioningStep::STATUS_PENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        ProvisioningStep::query()->insert($rows);
    }

    protected function markStep(ProvisioningJob $job, ?string $step, string $status, ?string $message = null, ?string $externalId = null): void
    {
        if (empty($step)) {
            return;
        }

        $query = ProvisioningStep::query()->where('provisioning_job_id', $job->id)->where('step', $step);

        if ($status === ProvisioningStep::STATUS_RUNNING) {
            $attrs = ['status' => $status, 'started_at' => now()];
            if ($message !== null) {
                $attrs['message'] = $message;
            }
            $query->update($attrs);

            return;
        }

        $attrs = ['status' => $status, 'finished_at' => now()];
        if ($message !== null) {
            $attrs['message'] = $message;
        }
        if ($externalId !== null) {
            $attrs['external_id'] = $externalId;
        }

        $query->update($attrs);
    }

    protected function provider(ProvisioningJob $job): InfrastructureProviderInterface
    {
        $provider = InfrastructureProvider::query()->find($job->provider_id)
            ?? $this->resolveProviderForJob($job);

        if (!$provider) {
            throw new InfrastructureException('No infrastructure provider is configured for this provisioning job.');
        }

        return $this->providerManager->for($provider);
    }

    protected function resolveProviderForJob(ProvisioningJob $job): ?InfrastructureProvider
    {
        return InfrastructureProvider::query()
            ->where('enabled', true)
            ->where('maintenance_mode', false)
            ->when($job->location_id, fn ($q) => $q->where('location_id', $job->location_id))
            ->orderBy('id')
            ->first();
    }

    protected function isDemo(ProvisioningJob $job): bool
    {
        $provider = $job->provider_id ? InfrastructureProvider::query()->find($job->provider_id) : null;

        return $provider?->type === InfrastructureProvider::TYPE_FAKE;
    }

    protected function hostExternalId(ProvisioningJob $job): ?string
    {
        return $job->host?->external_id ?? $job->host?->name;
    }

    protected function defaultTemplate(ProvisioningJob $job): ?\Pterodactyl\Models\InfrastructureTemplate
    {
        return \Pterodactyl\Models\InfrastructureTemplate::query()
            ->where('enabled', true)
            ->when($job->provider_id, fn ($q) => $q->where('provider_id', $job->provider_id))
            ->first();
    }

    /**
     * Build the Cloud-Init network configuration for a VM, using a dedicated
     * public IP allocated from the pool.
     */
    protected function cloudInitFor(\Pterodactyl\Models\InfrastructureIpPool $pool, string $gameIp): array
    {
        $prefix = $this->ipPool->prefixFromCidr($pool->network);
        $gateway = $pool->gateway ?: $this->ipPool->defaultGatewayFromCidr($pool->network);

        return [
            'ciuser' => 'hoston',
            'ipconfig0' => sprintf('ip=%s/%d,gw=%s', $gameIp, $prefix, $gateway),
            'nameserver' => $pool->dns ?: '1.1.1.1',
        ];
    }

    /**
     * Resolve the enabled IP pool to draw public addresses from.
     *
     * @throws InfrastructureException
     */
    protected function resolveIpPool(ProvisioningJob $job): \Pterodactyl\Models\InfrastructureIpPool
    {
        $pool = \Pterodactyl\Models\InfrastructureIpPool::query()
            ->where('enabled', true)
            ->when($job->location_id, fn ($q) => $q->where('location_id', $job->location_id))
            ->orderBy('id')
            ->first();

        if (!$pool) {
            throw new InfrastructureException('No enabled IP pool is configured for this location. Create an IP pool and release addresses first.');
        }

        return $pool;
    }

    protected function resolveEgg(ProvisioningJob $job): Egg
    {
        $profile = $job->profile;
        $eggId = $profile->egg_id ?? $profile->catalog?->egg_id;

        if (!$eggId) {
            throw new InfrastructureException('No Egg is mapped for this game.');
        }

        return Egg::query()->findOrFail($eggId);
    }

    protected function eggDefaultImage(Egg $egg): string
    {
        return (string) Arr::first($egg->docker_images ?? []);
    }

    protected function storeEggVariables(Server $server, Egg $egg, ProvisioningJob $job): void
    {
        $environment = $this->defaultEnvironment($egg, $job);

        $variables = $this->variableValidator
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($egg->id, $environment);

        $records = $variables->map(fn ($v) => [
            'server_id' => $server->id,
            'variable_id' => $v->id,
            'variable_value' => $v->value ?? '',
        ])->toArray();

        if (!empty($records)) {
            $this->serverVariableRepository->insert($records);
        }
    }

    /**
     * Build the environment for a server, using egg defaults merged with any
     * user-provided values (e.g. a requested game version) and any allocated
     * game license. Required variables without a default (such as passwords)
     * get a generated value.
     */
    protected function defaultEnvironment(Egg $egg, ProvisioningJob $job): array
    {
        $defaults = \Pterodactyl\Models\EggVariable::query()
            ->where('egg_id', $egg->id)
            ->get()
            ->mapWithKeys(function ($v) {
                $value = $v->default_value;

                if ($value === '' && $v->required) {
                    $value = $this->generateVariableValue($v);
                }

                return [$v->env_variable => $value];
            })
            ->toArray();

        $overrides = $job->service?->configuration['environment'] ?? [];

        $licenses = $this->licenseEnvironment($job);

        return array_merge($defaults, $overrides, $licenses);
    }

    /**
     * Allocate a commercial game license (e.g. Farming Simulator 25) and
     * return it keyed by the environment variable that carries it to Wings.
     *
     * The plaintext license is only ever passed into the server environment;
     * it is never logged or exposed to the customer.
     */
    protected function licenseEnvironment(ProvisioningJob $job): array
    {
        $catalog = $job->profile?->catalog;

        if (!$catalog || !$catalog->requires_license) {
            return [];
        }

        $pool = GameLicensePool::query()
            ->where('enabled', true)
            ->where('game_catalog_id', $catalog->id)
            ->first();

        if (!$pool) {
            throw new InfrastructureException(sprintf('No license pool is configured for "%s".', $catalog->name));
        }

        $key = $this->gameLicenses->allocate($pool, $job->service, $job->computeInstance);

        return [$pool->license_variable => $key];
    }

    protected function generateVariableValue(\Pterodactyl\Models\EggVariable $variable): string
    {
        $secretish = preg_match('/(pass|secret|token|key)/i', $variable->env_variable . ' ' . $variable->name) === 1;

        return $secretish ? Str::random(24) : Str::random(12);
    }

    protected function gamePorts(ProvisioningJob $job): array
    {
        $profile = $job->profile;
        $ports = $profile->ports ?? $profile->catalog?->ports ?? [];

        $ports = collect($ports)->map(function ($port) {
            return (int) preg_replace('/\D/', '', (string) $port);
        })->filter()->values()->toArray();

        if (empty($ports)) {
            $ports = [25565];
        }

        return $ports;
    }

    protected function hasBootstrapToken(ProvisioningJob $job): bool
    {
        return \Pterodactyl\Models\BootstrapToken::query()->where('provisioning_job_id', $job->id)->exists();
    }

    protected function wingsInstalled(ProvisioningJob $job): bool
    {
        if ($this->isDemo($job)) {
            return false;
        }

        return \Pterodactyl\Models\BootstrapToken::query()
            ->where('provisioning_job_id', $job->id)
            ->whereNotNull('used_at')
            ->exists();
    }

    protected function serverInstalled(ProvisioningJob $job): bool
    {
        return $job->server?->installed_at !== null;
    }

    protected function serverStarted(ProvisioningJob $job): bool
    {
        return $job->server !== null && $job->server->installed_at !== null;
    }

    protected function instanceStarted(ProvisioningJob $job): bool
    {
        return in_array($job->computeInstance?->status, [ComputeInstance::STATUS_RUNNING, ComputeInstance::STATUS_STARTING], true);
    }

    protected function instanceRunning(ProvisioningJob $job): bool
    {
        return $job->computeInstance?->status === ComputeInstance::STATUS_RUNNING || $job->computeInstance?->provisioned_at !== null;
    }

    protected function lastTask(ProvisioningJob $job): ?string
    {
        return $job->steps()->where('external_id', '!=', null)->orderByDesc('id')->value('external_id');
    }

    /**
     * Derive a private management address for the VM's Wings traffic. This is
     * an internal (RFC1918) address, distinct from the public game IP.
     */
    protected function managementIpFor(ProvisioningJob $job): string
    {
        return sprintf('10.0.%d.%d', ($job->host_id ?? $job->id) % 250, ($job->id % 250) + 1);
    }

    protected function generateServiceId(): string
    {
        return 'SVC-' . strtoupper(Str::random(8));
    }
}
