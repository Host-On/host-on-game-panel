<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Models\User;
use Pterodactyl\Models\GameService;
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

    public function providers(): View
    {
        return view('admin.hoston.providers', [
            'providers' => InfrastructureProvider::query()->with('location')->get(),
        ]);
    }

    public function storeProvider(): RedirectResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:proxmox,fake',
            'api_url' => 'nullable|string|max:191',
            'auth_user' => 'nullable|string|max:191',
            'auth_token' => 'nullable|string',
            'tls_verify' => 'sometimes|boolean',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $token = null;
        if (!empty($data['auth_token'])) {
            $token = $this->encrypter->encrypt($data['auth_token']);
        }

        InfrastructureProvider::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $data['name'],
            'type' => $data['type'],
            'api_url' => $data['api_url'] ?? null,
            'auth_user' => $data['auth_user'] ?? null,
            'auth_token' => $token,
            'tls_verify' => $data['tls_verify'] ?? true,
            'location_id' => $data['location_id'] ?? null,
            'enabled' => true,
            'maintenance_mode' => false,
        ]);

        $this->alert->success('Infrastructure provider created.')->flash();

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

    public function hosts(): View
    {
        return view('admin.hoston.hosts', [
            'hosts' => InfrastructureHost::query()->with(['cluster', 'location'])->get(),
        ]);
    }

    public function templates(): View
    {
        return view('admin.hoston.templates', [
            'templates' => InfrastructureTemplate::query()->with(['cluster', 'provider'])->get(),
            'providers' => InfrastructureProvider::query()->get(),
        ]);
    }

    public function ipPools(): View
    {
        return view('admin.hoston.ip-pools', [
            'pools' => InfrastructureIpPool::query()->with('location')->get(),
        ]);
    }

    public function catalog(): View
    {
        return view('admin.hoston.catalog', [
            'catalog' => GameCatalogEntry::query()->with('egg')->orderBy('sort_order')->get(),
            'profiles' => ResourceProfile::query()->with(['catalog', 'template'])->get(),
        ]);
    }

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
}
