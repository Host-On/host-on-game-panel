<?php

namespace Pterodactyl\Services\Infrastructure;

use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;

/**
 * Synchronizes the physical Proxmox nodes of a cluster into the local host
 * registry. Only physical facts (name, CPU, memory, disk, online status) are
 * synced; Host-On specific settings (enabled, maintenance, placement weight,
 * reserved capacity, allowed product classes, tags) are never overwritten.
 */
class HostSyncService
{
    public function __construct(protected InfrastructureProviderManager $providerManager)
    {
    }

    /**
     * Sync the nodes reported by a cluster into infrastructure_hosts.
     *
     * @return array{created: int, updated: int, total: int}
     */
    public function sync(InfrastructureCluster $cluster): array
    {
        $nodes = $this->providerManager->for($cluster)->getNodes();

        $created = 0;
        $updated = 0;

        foreach ($nodes as $node) {
            $attributes = [
                'name' => $node->name,
                'cpu_cores' => $node->cpu,
                'max_memory' => $node->maxMemory,
                'max_disk' => $node->maxDisk,
                'status' => $node->status,
                'last_synced_at' => now(),
            ];

            $existing = InfrastructureHost::query()
                ->where('cluster_id', $cluster->id)
                ->where('external_id', $node->name)
                ->first();

            if ($existing) {
                // Only touch physical attributes; preserve Host-On settings.
                $existing->update($attributes);
                $updated++;
            } else {
                InfrastructureHost::query()->create(array_merge([
                    'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                    'cluster_id' => $cluster->id,
                    'location_id' => $cluster->location_id,
                    'external_id' => $node->name,
                    'enabled' => true,
                    'maintenance_mode' => false,
                    'placement_weight' => 100,
                    'reserved_memory' => 0,
                    'reserved_disk' => 0,
                ], $attributes));
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => count($nodes)];
    }
}

