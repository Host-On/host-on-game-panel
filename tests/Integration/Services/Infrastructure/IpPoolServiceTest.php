<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\InfrastructureIpAllocation;
use Pterodactyl\Models\InfrastructureIpPool;
use Pterodactyl\Services\Infrastructure\IpPoolService;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class IpPoolServiceTest extends IntegrationTestCase
{
    protected IpPoolService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(IpPoolService::class);
    }

    private function makePool(array $attributes = []): InfrastructureIpPool
    {
        return InfrastructureIpPool::query()->create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Test Pool',
            'network' => '203.0.113.0/24',
            'gateway' => '203.0.113.1',
            'bridge' => 'vmbr0',
            'allocation_start' => '203.0.113.10',
            'allocation_end' => '203.0.113.12',
            'enabled' => true,
        ], $attributes));
    }

    private function makeInstance(): ComputeInstance
    {
        return ComputeInstance::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Test VM',
            'status' => ComputeInstance::STATUS_CREATING,
            'cpu' => 2,
            'memory' => 4096,
            'disk' => 40,
        ]);
    }

    public function test_sync_generates_addresses_in_range(): void
    {
        $pool = $this->makePool();

        $created = $this->service->sync($pool);

        $this->assertSame(3, $created);
        $this->assertSame(3, InfrastructureIpAllocation::query()->where('ip_pool_id', $pool->id)->count());
        $this->assertSame(
            ['203.0.113.10', '203.0.113.11', '203.0.113.12'],
            InfrastructureIpAllocation::query()->where('ip_pool_id', $pool->id)->pluck('address')->sort()->values()->all()
        );
    }

    public function test_sync_is_idempotent(): void
    {
        $pool = $this->makePool();

        $this->service->sync($pool);
        $created = $this->service->sync($pool);

        $this->assertSame(0, $created);
        $this->assertSame(3, InfrastructureIpAllocation::query()->where('ip_pool_id', $pool->id)->count());
    }

    public function test_allocate_assigns_first_available_address(): void
    {
        $pool = $this->makePool();
        $this->service->sync($pool);

        $allocation = $this->service->allocate($pool);

        $this->assertSame('203.0.113.10', $allocation->address);
        $this->assertSame(InfrastructureIpAllocation::STATUS_ALLOCATED, $allocation->status);
    }

    public function test_allocate_links_address_to_instance(): void
    {
        $pool = $this->makePool();
        $this->service->sync($pool);
        $instance = $this->makeInstance();

        $allocation = $this->service->allocate($pool, $instance);

        $this->assertSame($instance->id, $allocation->compute_instance_id);
    }

    public function test_allocate_throws_when_no_addresses_available(): void
    {
        $pool = $this->makePool(['allocation_start' => '203.0.113.10', 'allocation_end' => '203.0.113.10']);
        $this->service->sync($pool);

        $this->service->allocate($pool);

        $this->expectException(InfrastructureException::class);
        $this->service->allocate($pool);
    }

    public function test_release_for_instance_returns_address_to_pool(): void
    {
        $pool = $this->makePool();
        $this->service->sync($pool);
        $instance = $this->makeInstance();

        $allocation = $this->service->allocate($pool, $instance);
        $this->assertSame(InfrastructureIpAllocation::STATUS_ALLOCATED, $allocation->status);

        $this->service->releaseForInstance($instance);

        $allocation = $allocation->fresh();
        $this->assertSame(InfrastructureIpAllocation::STATUS_AVAILABLE, $allocation->status);
        $this->assertNull($allocation->compute_instance_id);
    }

    public function test_netmask_and_prefix_from_cidr(): void
    {
        $this->assertSame(24, $this->service->prefixFromCidr('203.0.113.0/24'));
        $this->assertSame('255.255.255.0', $this->service->netmaskFromCidr('203.0.113.0/24'));
    }
}
