<?php

namespace Pterodactyl\Console\Commands\HostOn;

use Illuminate\Console\Command;
use Pterodactyl\Models\User;
use Pterodactyl\Models\ProvisioningJob;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Jobs\ProcessProvisioningJob;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

/**
 * Provision a demo game service end-to-end. Useful for verifying the
 * provisioning pipeline and for demonstrating the platform without a browser.
 */
class DemoProvisionCommand extends Command
{
    protected $signature = 'hoston:provision-demo
                            {--product=minecraft-performance : Resource profile slug to provision}
                            {--name= : Optional service name}
                            {--user= : Username of the customer (defaults to demo)}';

    protected $description = 'Provision a demo game service end-to-end.';

    public function handle(ProvisioningService $provisioning): int
    {
        $username = $this->option('user') ?: 'demo';
        $user = User::query()->where('username', $username)->first();

        if (!$user) {
            $this->error("User \"{$username}\" does not exist.");

            return 1;
        }

        $product = $this->option('product');
        $profile = ResourceProfile::query()->where('slug', $product)->first();

        if (!$profile) {
            $this->error("Resource profile \"{$product}\" does not exist.");

            return 1;
        }

        $job = $provisioning->create([
            'user_id' => $user->id,
            'product' => $product,
            'location' => 'fra',
            'name' => $this->option('name') ?: ($profile->catalog?->name ?? $profile->name) . ' Server',
        ]);

        $this->info("Created provisioning job {$job->external_id} (id {$job->id}).");

        $status = $provisioning->run($job->fresh());

        $job = $job->fresh();
        $this->info("Final status: {$status}");
        $this->info("VMID: {$job->vmid} | Node: {$job->wings_node_id} | Server: {$job->server_id}");

        if ($job->error) {
            $this->error('Error: ' . $job->error);
        }

        foreach ($job->steps as $step) {
            $this->line("  - {$step->step}: {$step->status}" . ($step->message ? " ({$step->message})" : ''));
        }

        return $status === ProvisioningJob::STATUS_COMPLETED ? 0 : 1;
    }
}
