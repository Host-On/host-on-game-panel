<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\GameCatalogEntry;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Jobs\ProcessProvisioningJob;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

/**
 * Customer-facing game catalog and ordering. Customers see games and resource
 * profiles, never infrastructure internals.
 */
class HostOnOrderController extends ClientApiController
{
    public function __construct(protected ProvisioningService $provisioning)
    {
        parent::__construct();
    }

    public function catalog(): JsonResponse
    {
        $entries = GameCatalogEntry::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->with('profiles')
            ->get();

        return new JsonResponse(
            $entries->map(fn (GameCatalogEntry $entry) => [
                'id' => $entry->uuid,
                'name' => $entry->name,
                'slug' => $entry->slug,
                'description' => $entry->description,
                'artwork' => $entry->artwork,
                'profiles' => $entry->profiles->filter(fn ($p) => $p->enabled)->map(fn (ResourceProfile $p) => [
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'cpu' => $p->cpu,
                    'memory' => $p->memory,
                    'disk' => $p->disk,
                    'game_memory' => $p->game_memory,
                    'price' => $p->price,
                ])->values(),
            ])->values()
        );
    }

    public function order(): JsonResponse
    {
        $data = $this->request->validate([
            'product' => 'required|string|exists:resource_profiles,slug',
            'location' => 'nullable|string',
            'name' => 'nullable|string|max:191',
        ]);

        $job = $this->provisioning->create([
            'user_id' => $this->request->user()->id,
            'product' => $data['product'],
            'location' => $data['location'] ?? null,
            'name' => $data['name'] ?? null,
        ]);

        ProcessProvisioningJob::dispatch($job->id);

        return new JsonResponse([
            'service_id' => $job->service?->uuid,
            'provisioning_id' => $job->uuid,
            'status' => $job->status,
        ], 202);
    }
}
