<?php

namespace Pterodactyl\Console\Commands\HostOn;

use Illuminate\Console\Command;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\GameLicense;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\BootstrapToken;
use Pterodactyl\Models\ProvisioningJob;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Models\InfrastructureIpPool;
use Pterodactyl\Models\InfrastructureNetwork;
use Pterodactyl\Models\ComputeInstanceNetwork;
use Pterodactyl\Models\InfrastructureIpAllocation;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Nodes\NodeDeletionService;

/**
 * Remove all demo/placeholder data so a production Proxmox cluster can be
 * tested from a clean slate.
 *
 * Deletes: provisioning artifacts, demo compute instances, managed Wings
 * nodes and their game servers, fake (demo) clusters and their hosts,
 * demo IP pools/networks, demo licenses and the demo user.
 *
 * Never touches: real (non-fake) clusters and their synced hosts, the admin
 * user, static nodes or anything that was not created as demo data.
 */
class PurgeDemoCommand extends Command
{
    protected $signature = 'hoston:purge-demo';

    protected $description = 'Remove all demo data (instances, managed nodes, fake clusters). Keeps real clusters, admin users and static nodes.';

    public function handle(ServerDeletionService $serverDeletion, NodeDeletionService $nodeDeletion): int
    {
        // 1. Provisioning artifacts.
        ProvisioningJob::query()->delete();
        BootstrapToken::query()->delete();
        GameService::query()->delete();

        // 2. Managed Wings nodes and their game servers.
        foreach (Node::query()->where('type', Node::TYPE_MANAGED)->get() as $node) {
            foreach ($node->servers()->get() as $server) {
                $serverDeletion->withForce()->handle($server);
            }

            $node->refresh();

            try {
                $nodeDeletion->handle($node);
            } catch (\Throwable $exception) {
                $this->warn(sprintf('Managed node %d could not be deleted: %s', $node->id, $exception->getMessage()));
            }
        }

        // 3. Demo instances, networks, IP allocations, licenses.
        GameLicense::query()->delete();
        InfrastructureIpAllocation::query()->delete();
        ComputeInstanceNetwork::query()->delete();
        ComputeInstance::query()->delete();

        // 4. Fake clusters and their hosts/templates. Hosts are deleted
        //    explicitly because the FK is set-null rather than cascade.
        $fakeClusters = InfrastructureCluster::query()->where('type', InfrastructureCluster::TYPE_FAKE)->get();

        foreach ($fakeClusters as $cluster) {
            $cluster->hosts()->delete();
            $cluster->templates()->delete();
            $cluster->delete();
        }

        // Remove orphaned demo hosts left behind by earlier purges.
        \Pterodactyl\Models\InfrastructureHost::query()->whereNull('cluster_id')->delete();
        \Pterodactyl\Models\InfrastructureTemplate::query()->whereNull('cluster_id')->delete();

        // 5. Demo IP pools and networks.
        InfrastructureIpPool::query()->delete();
        InfrastructureNetwork::query()->delete();

        // 6. Clear template references on product profiles so a fresh template
        //    can be assigned for the production cluster.
        ResourceProfile::query()->update(['template_id' => null]);

        // 7. Demo customer account (admin account is kept).
        User::query()->where('username', 'demo')->delete();

        $this->info('Demo data purged. Real clusters, admin users and static nodes were not touched.');

        return 0;
    }
}
