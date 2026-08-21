<?php

namespace Pterodactyl\Console\Commands\HostOn;

use Illuminate\Console\Command;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Services\Infrastructure\HostSyncService;

/**
 * Synchronize hypervisor hosts for all (or a single) enabled Proxmox cluster.
 *
 * Physical facts (name, CPU, RAM, disk, online status) are pulled from the
 * Proxmox API and upserted into the local host registry. Host-On settings are
 * never overwritten.
 */
class SyncClusterHostsCommand extends Command
{
    protected $signature = 'hoston:sync-cluster-hosts
                            {cluster? : Optional cluster name to sync}';

    protected $description = 'Synchronize hypervisor hosts from all enabled Proxmox clusters.';

    public function handle(HostSyncService $hostSync): int
    {
        $clusters = InfrastructureCluster::query()
            ->where('enabled', true)
            ->when($this->argument('cluster'), fn ($q) => $q->where('name', $this->argument('cluster')))
            ->get();

        if ($clusters->isEmpty()) {
            $this->warn('No enabled clusters found to sync.');

            return 1;
        }

        foreach ($clusters as $cluster) {
            try {
                $result = $hostSync->sync($cluster);
                $this->info(sprintf(
                    '[%s] %d created, %d updated (%d total from Proxmox).',
                    $cluster->name,
                    $result['created'],
                    $result['updated'],
                    $result['total']
                ));
            } catch (\Throwable $exception) {
                $this->error(sprintf('[%s] sync failed: %s', $cluster->name, $exception->getMessage()));
            }
        }

        return 0;
    }
}
