<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\ProvisioningJob;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Jobs\ProcessProvisioningJob;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Models\GameCatalogEntry;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\InfrastructureIpPool;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Models\InfrastructureProvider;
use Pterodactyl\Models\InfrastructureTemplate;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Models\InfrastructureIpAllocation;
use Pterodactyl\Services\Infrastructure\IpPoolService;
use Pterodactyl\Services\Infrastructure\PlacementEngine;
use Pterodactyl\Services\Infrastructure\InfrastructureProviderManager;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

/**
 * Host-On Games infrastructure administration.
 */
class HostOnController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected Encrypter $encrypter,
        protected InfrastructureProviderManager $providerManager,
        protected ProvisioningService $provisioning,
        protected IpPoolService $ipPool,
    ) {
    }

    public function index(): View
    {
        $clusterCount = InfrastructureCluster::query()->count();
        $hostCount = InfrastructureHost::query()->count();
        $vmCount = \Pterodactyl\Models\ComputeInstance::query()->count();
        $healthyVm = \Pterodactyl\Models\ComputeInstance::query()->where('status', 'running')->count();
        $provisioning = ProvisioningJob::query()->whereIn('status', ['pending', 'running'])->count();
        $failed = ProvisioningJob::query()->where('status', 'failed')->count();

        $hosts = InfrastructureHost::query()->with('cluster')->get();

        return view('admin.hoston.index', [
            'clusterCount' => $clusterCount,
            'hostCount' => $hostCount,
            'vmCount' => $vmCount,
            'healthyVm' => $healthyVm,
            'provisioning' => $provisioning,
            'failed' => $failed,
            'hosts' => $hosts,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Providers (Proxmox connections)
    |--------------------------------------------------------------------------
    */

    public function providers(): View
    {
        return view('admin.hoston.providers', [
            'providers' => InfrastructureProvider::query()->with('location')->get(),
            'locations' => Location::query()->get(),
        ]);
    }

    public function storeProvider(): RedirectResponse
    {
        $data = $this->providerData();

        InfrastructureProvider::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'type' => $data['type'],
            'api_url' => $data['api_url'] ?? null,
            'auth_user' => $data['auth_user'] ?? null,
            'auth_token' => $this->encryptToken($data['auth_token'] ?? null),
            'tls_verify' => !empty($data['tls_verify']),
            'location_id' => $data['location_id'] ?? null,
            'enabled' => $data['enabled'] ?? true,
            'maintenance_mode' => false,
        ]);

        $this->alert->success('Infrastructure provider created.')->flash();

        return redirect()->route('admin.hoston.providers');
    }

    public function updateProvider(InfrastructureProvider $provider): RedirectResponse
    {
        $data = $this->providerData();

        $fields = [
            'name' => $data['name'],
            'type' => $data['type'],
            'api_url' => $data['api_url'] ?? null,
            'auth_user' => $data['auth_user'] ?? null,
            'tls_verify' => !empty($data['tls_verify']),
            'location_id' => $data['location_id'] ?? null,
            'enabled' => !empty($data['enabled']),
        ];

        // Only overwrite the token when a new one is provided.
        if (!empty($data['auth_token'])) {
            $fields['auth_token'] = $this->encryptToken($data['auth_token']);
        }

        $provider->update($fields);

        $this->alert->success('Infrastructure provider updated.')->flash();

        return redirect()->route('admin.hoston.providers');
    }

    public function deleteProvider(InfrastructureProvider $provider): RedirectResponse
    {
        $provider->delete();
        $this->alert->success('Infrastructure provider deleted.')->flash();

        return redirect()->route('admin.hoston.providers');
    }

    public function testProvider(InfrastructureProvider $provider): RedirectResponse
    {
        try {
            $this->providerManager->for($provider)->testConnection();
            $provider->update(['status' => 'healthy', 'last_checked_at' => now()]);
            $this->alert->success('Connection successful.')->flash();
        } catch (\Throwable $exception) {
            $provider->update(['status' => 'error', 'last_checked_at' => now()]);
            $this->alert->danger('Connection failed: ' . $exception->getMessage())->flash();
        }

        return redirect()->route('admin.hoston.providers');
    }

    /*
    |--------------------------------------------------------------------------
    | Clusters
    |--------------------------------------------------------------------------
    */

    public function clusters(): View
    {
        return view('admin.hoston.clusters', [
            'clusters' => InfrastructureCluster::query()->with(['provider', 'location'])->withCount('hosts')->get(),
            'providers' => InfrastructureProvider::query()->where('enabled', true)->get(),
            'locations' => Location::query()->get(),
        ]);
    }

    public function storeCluster(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'provider_id' => 'required|exists:infrastructure_providers,id',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        InfrastructureCluster::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'provider_id' => $data['provider_id'],
            'location_id' => $data['location_id'] ?? null,
            'enabled' => true,
            'maintenance_mode' => false,
        ]);

        $this->alert->success('Cluster created.')->flash();

        return redirect()->route('admin.hoston.clusters');
    }

    public function deleteCluster(InfrastructureCluster $cluster): RedirectResponse
    {
        $cluster->delete();
        $this->alert->success('Cluster deleted.')->flash();

        return redirect()->route('admin.hoston.clusters');
    }

    /*
    |--------------------------------------------------------------------------
    | Compute hosts (Proxmox nodes)
    |--------------------------------------------------------------------------
    */

    public function hosts(): View
    {
        return view('admin.hoston.hosts', [
            'hosts' => InfrastructureHost::query()->with(['cluster', 'location'])->get(),
            'clusters' => InfrastructureCluster::query()->with('provider')->get(),
            'locations' => Location::query()->get(),
        ]);
    }

    public function storeHost(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'hostname' => 'nullable|string|max:191',
            'external_id' => 'nullable|string|max:191',
            'cluster_id' => 'required|exists:infrastructure_clusters,id',
            'location_id' => 'nullable|exists:locations,id',
            'cpu_cores' => 'required|integer|min:1',
            'memory_gb' => 'required|integer|min:1',
            'disk_gb' => 'required|integer|min:1',
        ]);

        $cluster = InfrastructureCluster::query()->findOrFail($data['cluster_id']);

        InfrastructureHost::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'hostname' => $data['hostname'] ?? null,
            'external_id' => $data['external_id'] ?? $data['name'],
            'provider_id' => $cluster->provider_id,
            'cluster_id' => $data['cluster_id'],
            'location_id' => $data['location_id'] ?? null,
            'cpu_cores' => (int) $data['cpu_cores'],
            'max_memory' => (int) $data['memory_gb'] * 1024,
            'max_disk' => (int) $data['disk_gb'],
            'enabled' => true,
            'maintenance_mode' => false,
        ]);

        $this->alert->success('Compute node created.')->flash();

        return redirect()->route('admin.hoston.hosts');
    }

    public function deleteHost(InfrastructureHost $host): RedirectResponse
    {
        $host->delete();
        $this->alert->success('Compute node deleted.')->flash();

        return redirect()->route('admin.hoston.hosts');
    }

    /*
    |--------------------------------------------------------------------------
    | VM Templates
    |--------------------------------------------------------------------------
    */

    public function templates(): View
    {
        return view('admin.hoston.templates', [
            'templates' => InfrastructureTemplate::query()->with(['cluster', 'provider'])->get(),
            'providers' => InfrastructureProvider::query()->get(),
            'clusters' => InfrastructureCluster::query()->get(),
        ]);
    }

    public function storeTemplate(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'provider_id' => 'required|exists:infrastructure_providers,id',
            'cluster_id' => 'nullable|exists:infrastructure_clusters,id',
            'template_vmid' => 'required|integer|min:1',
            'storage' => 'required|string|max:191',
            'bridge' => 'required|string|max:191',
            'default_cpu' => 'nullable|integer|min:1',
            'default_memory_gb' => 'nullable|integer|min:1',
            'default_disk' => 'nullable|integer|min:1',
            'cloud_init_enabled' => 'sometimes|boolean',
            'wings_bootstrap_enabled' => 'sometimes|boolean',
        ]);

        InfrastructureTemplate::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'provider_id' => $data['provider_id'],
            'cluster_id' => $data['cluster_id'] ?? null,
            'template_vmid' => (int) $data['template_vmid'],
            'storage' => $data['storage'],
            'bridge' => $data['bridge'],
            'cloud_init_enabled' => $data['cloud_init_enabled'] ?? false,
            'wings_bootstrap_enabled' => $data['wings_bootstrap_enabled'] ?? false,
            'default_cpu' => (int) ($data['default_cpu'] ?? 2),
            'default_memory' => (int) ($data['default_memory_gb'] ?? 8) * 1024,
            'default_disk' => (int) ($data['default_disk'] ?? 40),
            'enabled' => true,
        ]);

        $this->alert->success('VM template created.')->flash();

        return redirect()->route('admin.hoston.templates');
    }

    public function deleteTemplate(InfrastructureTemplate $template): RedirectResponse
    {
        $template->delete();
        $this->alert->success('VM template deleted.')->flash();

        return redirect()->route('admin.hoston.templates');
    }

    /*
    |--------------------------------------------------------------------------
    | IP Pools
    |--------------------------------------------------------------------------
    */

    public function ipPools(): View
    {
        return view('admin.hoston.ip-pools', [
            'pools' => InfrastructureIpPool::query()->with('location')->get(),
            'locations' => Location::query()->get(),
        ]);
    }

    public function storeIpPool(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'network' => 'required|string|max:191',
            'gateway' => 'nullable|string|max:191',
            'bridge' => 'required|string|max:191',
            'dns' => 'nullable|string|max:191',
            'allocation_start' => 'nullable|string|max:191',
            'allocation_end' => 'nullable|string|max:191',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        InfrastructureIpPool::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'network' => $data['network'],
            'subnet' => null,
            'gateway' => $data['gateway'] ?? null,
            'bridge' => $data['bridge'],
            'dns' => $data['dns'] ?? null,
            'allocation_start' => $data['allocation_start'] ?? null,
            'allocation_end' => $data['allocation_end'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'enabled' => true,
        ]);

        $this->alert->success('IP pool created.')->flash();

        return redirect()->route('admin.hoston.ip-pools');
    }

    public function deleteIpPool(InfrastructureIpPool $pool): RedirectResponse
    {
        $pool->delete();
        $this->alert->success('IP pool deleted.')->flash();

        return redirect()->route('admin.hoston.ip-pools');
    }

    public function ipPoolView(InfrastructureIpPool $pool): View
    {
        return view('admin.hoston.ip-pool-view', [
            'pool' => $pool,
            'allocations' => InfrastructureIpAllocation::query()
                ->where('ip_pool_id', $pool->id)
                ->with('instance')
                ->orderByRaw('INET_ATON(address)')
                ->get(),
            'available' => InfrastructureIpAllocation::query()->where('ip_pool_id', $pool->id)->where('status', InfrastructureIpAllocation::STATUS_AVAILABLE)->count(),
            'allocated' => InfrastructureIpAllocation::query()->where('ip_pool_id', $pool->id)->where('status', InfrastructureIpAllocation::STATUS_ALLOCATED)->count(),
            'reserved' => InfrastructureIpAllocation::query()->where('ip_pool_id', $pool->id)->where('status', InfrastructureIpAllocation::STATUS_RESERVED)->count(),
        ]);
    }

    public function syncIpPool(InfrastructureIpPool $pool): RedirectResponse
    {
        $created = $this->ipPool->sync($pool);
        $this->alert->success(sprintf('%d address(es) released into the pool.', $created))->flash();

        return redirect()->route('admin.hoston.ip-pools.view', $pool->id);
    }

    public function storeIp(InfrastructureIpPool $pool): RedirectResponse
    {
        $data = request()->validate(['address' => 'required|ip']);

        $this->ipPool->addAddress($pool, $data['address']);
        $this->alert->success('IP address released into the pool.')->flash();

        return redirect()->route('admin.hoston.ip-pools.view', $pool->id);
    }

    public function releaseIp(InfrastructureIpPool $pool, InfrastructureIpAllocation $allocation): RedirectResponse
    {
        $this->ipPool->release($allocation);
        $this->alert->success('IP address released.')->flash();

        return redirect()->route('admin.hoston.ip-pools.view', $pool->id);
    }

    public function reserveIp(InfrastructureIpPool $pool, InfrastructureIpAllocation $allocation): RedirectResponse
    {
        $this->ipPool->reserve($allocation);
        $this->alert->success('IP address reserved.')->flash();

        return redirect()->route('admin.hoston.ip-pools.view', $pool->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Game catalog + resource profiles
    |--------------------------------------------------------------------------
    */

    public function catalog(): View
    {
        return view('admin.hoston.catalog', [
            'catalog' => GameCatalogEntry::query()->with('egg')->orderBy('sort_order')->get(),
            'profiles' => ResourceProfile::query()->with(['catalog', 'template'])->get(),
            'nests' => Nest::query()->with('eggs')->get(),
            'templates' => InfrastructureTemplate::query()->get(),
            'locations' => Location::query()->get(),
        ]);
    }

    public function storeCatalog(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'slug' => 'required|string|max:191|unique:game_catalog_entries,slug',
            'description' => 'nullable|string',
            'egg_id' => 'nullable|exists:eggs,id',
            'default_image' => 'nullable|string|max:191',
            'min_ram' => 'nullable|integer|min:0',
            'recommended_ram' => 'nullable|integer|min:0',
            'ports' => 'nullable|string',
        ]);

        $egg = !empty($data['egg_id']) ? Egg::query()->find($data['egg_id']) : null;

        GameCatalogEntry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'nest_id' => $egg?->nest_id,
            'egg_id' => $egg?->id,
            'default_image' => $data['default_image'] ?? ($egg ? (string) array_values($egg->docker_images ?? [])[0] : null),
            'min_ram' => (int) ($data['min_ram'] ?? 1024),
            'recommended_ram' => (int) ($data['recommended_ram'] ?? 4096),
            'ports' => $this->parsePorts($data['ports'] ?? null),
            'environment' => [],
            'enabled' => true,
            'sort_order' => 0,
        ]);

        $this->alert->success('Game added to catalog.')->flash();

        return redirect()->route('admin.hoston.catalog');
    }

    public function deleteCatalog(GameCatalogEntry $catalog): RedirectResponse
    {
        $catalog->delete();
        $this->alert->success('Game removed from catalog.')->flash();

        return redirect()->route('admin.hoston.catalog');
    }

    public function storeProfile(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'slug' => 'required|string|max:191|unique:resource_profiles,slug',
            'description' => 'nullable|string',
            'game_catalog_id' => 'required|exists:game_catalog_entries,id',
            'cpu' => 'required|integer|min:1',
            'memory_gb' => 'required|integer|min:1',
            'disk_gb' => 'required|integer|min:1',
            'game_cpu' => 'required|integer|min:1',
            'game_memory_gb' => 'required|integer|min:1',
            'game_disk_gb' => 'required|integer|min:1',
            'backups' => 'nullable|integer|min:0',
            'infrastructure_type' => 'required|in:dedicated_vm,static,shared',
            'template_id' => 'nullable|exists:infrastructure_templates,id',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $catalog = GameCatalogEntry::query()->findOrFail($data['game_catalog_id']);
        $memory = (int) $data['memory_gb'] * 1024;
        $gameMemory = (int) $data['game_memory_gb'] * 1024;

        ResourceProfile::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'game_catalog_id' => $catalog->id,
            'nest_id' => $catalog->nest_id,
            'egg_id' => $catalog->egg_id,
            'cpu' => (int) $data['cpu'],
            'memory' => $memory,
            'disk' => (int) $data['disk_gb'],
            'game_cpu' => (int) $data['game_cpu'],
            'game_memory' => $gameMemory,
            'game_disk' => (int) $data['game_disk_gb'] * 1024,
            'system_reserve' => max(0, $memory - $gameMemory),
            'ports' => $catalog->ports,
            'backups' => (int) ($data['backups'] ?? 0),
            'infrastructure_type' => $data['infrastructure_type'],
            'template_id' => $data['template_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'enabled' => true,
        ]);

        $this->alert->success('Resource profile created.')->flash();

        return redirect()->route('admin.hoston.catalog');
    }

    public function deleteProfile(ResourceProfile $profile): RedirectResponse
    {
        $profile->delete();
        $this->alert->success('Resource profile deleted.')->flash();

        return redirect()->route('admin.hoston.catalog');
    }

    /*
    |--------------------------------------------------------------------------
    | Provisioning
    |--------------------------------------------------------------------------
    */

    public function provisioning(): View
    {
        return view('admin.hoston.provisioning', [
            'jobs' => ProvisioningJob::query()->with(['service', 'profile', 'host'])->orderByDesc('id')->paginate(25),
        ]);
    }

    public function provisioningView(ProvisioningJob $job): View
    {
        return view('admin.hoston.provisioning-view', [
            'job' => $job->load(['steps', 'service', 'profile', 'host', 'computeInstance', 'server', 'node', 'user']),
        ]);
    }

    public function retry(ProvisioningJob $job): RedirectResponse
    {
        if (!in_array($job->status, ['failed', 'waiting_for_bootstrap'], true)) {
            $this->alert->warning('This provisioning job is not in a retryable state.')->flash();

            return redirect()->route('admin.hoston.provisioning.view', $job->id);
        }

        $job->update(['status' => 'pending', 'error' => null, 'failed_at' => null]);
        ProcessProvisioningJob::dispatch($job->id);

        $this->alert->success('Provisioning job re-queued.')->flash();

        return redirect()->route('admin.hoston.provisioning.view', $job->id);
    }

    public function createService(): RedirectResponse
    {
        $data = request()->validate([
            'user_id' => 'required|integer|exists:users,id',
            'product' => 'required|string|exists:resource_profiles,slug',
            'location' => 'nullable|string',
            'name' => 'nullable|string|max:191',
        ]);

        $job = $this->provisioning->create($data, request()->user());
        ProcessProvisioningJob::dispatch($job->id);

        $this->alert->success('Provisioning started.')->flash();

        return redirect()->route('admin.hoston.provisioning.view', $job->id);
    }

    public function placement(): View
    {
        $profile = request()->query('profile');
        $hosts = InfrastructureHost::query()->with('cluster')->get();

        $ranked = [];
        if ($profile) {
            $p = ResourceProfile::query()->where('slug', $profile)->first();
            if ($p) {
                $ranked = app(PlacementEngine::class)->rank($hosts, $p->cpu, $p->memory, $p->disk);
            }
        }

        return view('admin.hoston.placement', [
            'profiles' => ResourceProfile::query()->get(),
            'ranked' => $ranked,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function providerData(): array
    {
        return request()->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:proxmox,fake',
            'api_url' => 'nullable|string|max:191',
            'auth_user' => 'nullable|string|max:191',
            'auth_token' => 'nullable|string',
            'tls_verify' => 'sometimes|boolean',
            'location_id' => 'nullable|exists:locations,id',
            'enabled' => 'sometimes|boolean',
        ]);
    }

    protected function encryptToken(?string $token): ?string
    {
        return !empty($token) ? $this->encrypter->encrypt($token) : null;
    }

    /**
     * Parse a comma/newline separated list of ports into an array.
     *
     * @return array<int, string>
     */
    protected function parsePorts(?string $ports): array
    {
        if (empty($ports)) {
            return [];
        }

        return collect(preg_split('/[\s,]+/', $ports))
            ->map(fn ($port) => trim($port))
            ->filter()
            ->values()
            ->toArray();
    }
}
