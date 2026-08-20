<?php

namespace Pterodactyl\Tests\Unit\Services\Infrastructure;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Services\Infrastructure\PlacementEngine;

class PlacementEngineTest extends TestCase
{
    private function makeHost(array $attributes = []): InfrastructureHost
    {
        return new InfrastructureHost(array_merge([
            'id' => 1,
            'name' => 'game-pve01',
            'enabled' => true,
            'maintenance_mode' => false,
            'cpu_cores' => 32,
            'max_memory' => 131072,
            'max_disk' => 2048,
            'allocated_memory' => 0,
            'allocated_disk' => 0,
            'cpu_utilization' => 30,
            'memory_utilization' => 40,
            'disk_utilization' => 30,
        ], $attributes));
    }

    public function test_it_excludes_disabled_hosts(): void
    {
        $host = $this->makeHost(['enabled' => false]);

        $result = (new PlacementEngine())->score($host, 4, 8192, 80);

        $this->assertTrue($result['excluded']);
        $this->assertSame(0.0, $result['score']);
    }

    public function test_it_excludes_hosts_in_maintenance(): void
    {
        $host = $this->makeHost(['maintenance_mode' => true]);

        $result = (new PlacementEngine())->score($host, 4, 8192, 80);

        $this->assertTrue($result['excluded']);
    }

    public function test_it_excludes_hosts_with_insufficient_memory(): void
    {
        $host = $this->makeHost(['max_memory' => 4096]);

        $result = (new PlacementEngine())->score($host, 4, 8192, 80);

        $this->assertTrue($result['excluded']);
        $this->assertStringContainsString('Insufficient RAM', $result['reasons'][0]);
    }

    public function test_it_excludes_hosts_with_insufficient_disk(): void
    {
        $host = $this->makeHost(['max_disk' => 40]);

        $result = (new PlacementEngine())->score($host, 4, 8192, 80);

        $this->assertTrue($result['excluded']);
        $this->assertStringContainsString('Insufficient storage', $result['reasons'][0]);
    }

    public function test_it_prefers_a_host_with_more_headroom(): void
    {
        $engine = new PlacementEngine();

        $busy = $this->makeHost([
            'id' => 1,
            'name' => 'game-pve01',
            'max_memory' => 131072,
            'allocated_memory' => 120000,
            'cpu_utilization' => 72,
            'memory_utilization' => 90,
        ]);
        $idle = $this->makeHost([
            'id' => 2,
            'name' => 'game-pve02',
            'max_memory' => 262144,
            'allocated_memory' => 60000,
            'cpu_utilization' => 31,
            'memory_utilization' => 33,
        ]);

        $ranked = $engine->rank(collect([$busy, $idle]), 6, 16384, 100);

        $this->assertFalse($ranked[0]['excluded']);
        $this->assertSame('game-pve02', $ranked[0]['host']->name);
        $this->assertGreaterThan($ranked[1]['score'], $ranked[0]['score']);
    }

    public function test_it_returns_reasons_for_selection(): void
    {
        $host = $this->makeHost();

        $result = (new PlacementEngine())->score($host, 4, 8192, 80);

        $this->assertFalse($result['excluded']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertGreaterThan(0, $result['score']);
    }
}
