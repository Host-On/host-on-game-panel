<?php

namespace Pterodactyl\Http\Controllers\Api\Application\HostOn;

use Pterodactyl\Models\GameService;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Jobs\ProcessProvisioningJob;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;
use Pterodactyl\Services\Infrastructure\Provisioning\ServiceLifecycleService;

/**
 * External provisioning API used by the Host-On.Games shop/billing system.
 *
 * This is a clean, high-level API: the billing system triggers provisioning
 * by product slug + location and never talks to Proxmox directly.
 *
 * Endpoint: /api/application/hoston/services
 */
class ServiceController extends ApplicationApiController
{
    public function __construct(
        protected ProvisioningService $provisioning,
        protected ServiceLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        $services = GameService::query()
            ->when($this->request->filled('user_id'), fn ($q) => $q->where('user_id', $this->request->integer('user_id')))
            ->with(['server', 'computeInstance', 'profile', 'catalog', 'location'])
            ->paginate($this->request->query('per_page') ?? 50);

        return new JsonResponse([
            'data' => $services->through(fn (GameService $service) => [
                'service_id' => $service->uuid,
                'external_id' => $service->external_id,
                'name' => $service->name,
                'status' => $service->status,
                'user_id' => $service->user_id,
                'product' => $service->profile?->slug,
                'location' => $service->location?->short,
                'server_id' => $service->server?->uuid,
                'created_at' => $service->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'pagination' => [
                    'total' => $services->total(),
                    'per_page' => $services->perPage(),
                    'current_page' => $services->currentPage(),
                    'total_pages' => $services->lastPage(),
                ],
            ],
        ]);
    }

    public function store(): JsonResponse
    {
        $data = $this->request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'product' => 'required|string',
            'location' => 'nullable|string',
            'name' => 'nullable|string|max:191',
            'configuration' => 'nullable|array',
        ]);

        $job = $this->provisioning->create($data);

        ProcessProvisioningJob::dispatch($job->id);

        return new JsonResponse([
            'service_id' => $job->service?->uuid,
            'service_external_id' => $job->service?->external_id,
            'provisioning_id' => $job->uuid,
            'provisioning_external_id' => $job->external_id,
            'status' => $job->status,
        ], 202);
    }

    public function show(GameService $service): JsonResponse
    {
        $job = $service->provisioningJobs()->latest()->first();

        return new JsonResponse([
            'service_id' => $service->uuid,
            'external_id' => $service->external_id,
            'status' => $service->status,
            'name' => $service->name,
            'server_id' => $service->server?->uuid,
            'compute_instance' => $service->computeInstance?->uuid,
            'provisioning' => $job ? [
                'id' => $job->uuid,
                'external_id' => $job->external_id,
                'status' => $job->status,
                'current_step' => $job->current_step,
                'error' => $job->error,
                'created_at' => $job->created_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function suspend(GameService $service): JsonResponse
    {
        $this->lifecycle->suspend($service);

        return new JsonResponse(['status' => $service->status]);
    }

    public function unsuspend(GameService $service): JsonResponse
    {
        $this->lifecycle->unsuspend($service);

        return new JsonResponse(['status' => $service->status]);
    }

    public function resize(GameService $service): JsonResponse
    {
        $data = $this->request->validate([
            'cpu' => 'required|integer|min:1',
            'memory' => 'required|integer|min:1',
            'disk' => 'required|integer|min:1',
        ]);

        $this->lifecycle->resize($service, $data['cpu'], $data['memory'], $data['disk']);

        return new JsonResponse(['status' => $service->status]);
    }

    public function destroy(GameService $service): JsonResponse
    {
        $this->lifecycle->terminate($service);

        return new JsonResponse(['status' => $service->status]);
    }
}
