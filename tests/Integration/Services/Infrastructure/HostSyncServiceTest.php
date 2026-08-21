<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Services\Infrastructure\HostSyncService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class HostSyncServiceTest extends IntegrationTestCase
{
    protected HostSyncService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(HostSyncService::class);
    }

    private function makeProvider(): InfrastructureCluster
    {
        return InfrastructureCluster::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Sync Test Cluster',
            'type' => InfrastructureCluster::TYPE_FAKE,
            'enabled' => true,
            'maintenance_mode' => false,
        ]);
    }

    public function test_sync_creates_hosts_from_provider(): void
    {
        $provider = $this->makeProvider();

        $result = $this->service->sync($provider);

        $this->assertGreaterThan(0, $result['total']);
        $this->assertSame($result['total'], $result['created']);

        $host = InfrastructureHost::query()->where('cluster_id', $provider->id)->where('external_id', 'test-pve01')->first();
        $this->assertNotNull($host);
        $this->assertSame('online', $host->status);
        $this->assertGreaterThan(0, $host->max_memory);
        $this->assertGreaterThan(0, $host->cpu_cores);
    }

    public function test_sync_preserves_host_on_settings(): void
    {
        $provider = $this->makeProvider();

        // Pre-seed a host with local Host-On settings.
        $host = InfrastructureHost::query()->create([
            'uuid' => Str::uuid()->toString(),
            'cluster_id' => $provider->id,
            'name' => 'test-pve01',
            'external_id' => 'test-pve01',
            'enabled' => false,
            'maintenance_mode' => true,
            'placement_weight' => 33,
            'allowed_product_classes' => ['minecraft'],
            'cpu_cores' => 1,
            'max_memory' => 1,
            'max_disk' => 1,
        ]);

        $this->service->sync($provider);

        $host = $host->fresh();

        // Physical facts are synced.
        $this->assertSame('online', $host->status);
        $this->assertGreaterThan(1, $host->max_memory);

        // Host-On settings are preserved.
        $this->assertFalse($host->enabled);
        $this->assertTrue($host->maintenance_mode);
        $this->assertSame(33, $host->placement_weight);
        $this->assertSame(['minecraft'], $host->allowed_product_classes);
    }
}
