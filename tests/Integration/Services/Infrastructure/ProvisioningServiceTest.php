<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Pterodactyl\Models\User;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\ProvisioningJob;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

class ProvisioningServiceTest extends IntegrationTestCase
{
    protected ProvisioningService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ProvisioningService::class);
    }

    public function test_demo_provisioning_completes_end_to_end(): void
    {
        $user = User::query()->where('username', 'demo')->firstOrFail();
        $profile = ResourceProfile::query()->where('slug', 'minecraft-performance')->firstOrFail();

        $job = $this->service->create([
            'user_id' => $user->id,
            'product' => $profile->slug,
            'location' => 'fra',
            'name' => 'Minecraft Survival',
        ]);

        $status = $this->service->run($job->fresh());

        $this->assertSame(ProvisioningJob::STATUS_COMPLETED, $status);

        $job = $job->fresh();

        $this->assertNotNull($job->vmid);
        $this->assertNotNull($job->wings_node_id);
        $this->assertNotNull($job->server_id);
        $this->assertNotNull($job->compute_instance_id);
        $this->assertNotNull($job->host_id);

        $this->assertSame(GameService::STATUS_ACTIVE, $job->service->status);
        $this->assertNotNull($job->server->installed_at);

        $this->assertSame(ProvisioningJob::STATUS_COMPLETED, $job->status);

        // Every step should have reached a terminal state.
        foreach ($job->steps as $step) {
            $this->assertContains($step->status, ['success', 'skipped'], "Step {$step->step} did not succeed.");
        }
    }

    public function test_provisioning_records_external_ids_for_resume(): void
    {
        $user = User::query()->where('username', 'demo')->firstOrFail();
        $profile = ResourceProfile::query()->where('slug', 'rust-performance')->firstOrFail();

        $job = $this->service->create([
            'user_id' => $user->id,
            'product' => $profile->slug,
            'location' => 'fra',
            'name' => 'Rust Server',
        ]);

        $this->service->run($job->fresh());

        $job = $job->fresh();

        $this->assertNotNull($job->vmid, 'VMID must be recorded for resume support.');
        $this->assertNotNull($job->server_id, 'Server ID must be recorded for resume support.');
    }
}
