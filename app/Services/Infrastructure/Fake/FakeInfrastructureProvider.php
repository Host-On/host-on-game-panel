<?php

namespace Pterodactyl\Services\Infrastructure\Fake;

use Pterodactyl\Services\Infrastructure\Objects\HostMetrics;
use Pterodactyl\Services\Infrastructure\Objects\HostSummary;
use Pterodactyl\Services\Infrastructure\Objects\InstanceResult;
use Pterodactyl\Services\Infrastructure\Objects\InstanceSpec;
use Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface;

/**
 * Simulated infrastructure provider used for development and preview
 * environments where no real Proxmox cluster is available.
 *
 * It simulates a cluster with several nodes, capacity accounting, VM
 * creation and the full VM lifecycle. This is explicitly a demo/dev only
 * implementation and is not a replacement for ProxmoxInfrastructureProvider.
 */
class FakeInfrastructureProvider implements InfrastructureProviderInterface
{
    /** @var array<string, array{name:string, status:string, cpu:int, memory:int, disk:int, task:string}> */
    protected static array $vms = [];

    protected int $nextVmid = 18000;

    public function __construct(protected ?string $clusterName = null)
    {
        // Seed a stable starting VMID range so demo VMs look believable but are
        // clearly synthetic.
        $this->nextVmid = 18000 + (count(static::$vms) * 1);
    }

    public function testConnection(): void
    {
        // Always succeeds for the demo provider.
    }

    public function getNodes(): array
    {
        // Simulated hypervisors: each cluster reports its own set of nodes.
        if ($this->clusterName !== null && str_contains(strtolower($this->clusterName), 'test')) {
            return [
                new HostSummary('test-pve01', 'online', 16, 65536, 1024),
                new HostSummary('test-pve02', 'online', 16, 65536, 1024),
            ];
        }

        return [
            new HostSummary('game-pve01', 'online', 32, 131072, 2048),
            new HostSummary('game-pve02', 'online', 64, 262144, 4096),
            new HostSummary('game-pve03', 'online', 32, 131072, 2048),
            new HostSummary('game-pve04', 'maintenance', 32, 131072, 2048),
        ];
    }

    public function getNextVmId(): int
    {
        return $this->nextVmid++;
    }

    public function createInstance(InstanceSpec $spec): InstanceResult
    {
        $node = $spec->templateNode ?? 'game-pve02';
        $vmid = (string) $this->getNextVmId();

        static::$vms[$vmid] = [
            'name' => $spec->name,
            'status' => 'creating',
            'cpu' => $spec->cpu,
            'memory' => $spec->memory,
            'disk' => $spec->disk,
            'task' => 'UPID:demo:' . $vmid,
        ];

        // Simulate the asynchronous clone completing immediately.
        static::$vms[$vmid]['status'] = 'stopped';

        return new InstanceResult(vmid: $vmid, task: 'UPID:demo:' . $vmid, node: $node);
    }

    public function deleteInstance(string $vmid, ?string $node = null): void
    {
        unset(static::$vms[$vmid]);
    }

    public function startInstance(string $vmid, ?string $node = null): void
    {
        if (isset(static::$vms[$vmid])) {
            static::$vms[$vmid]['status'] = 'running';
        }
    }

    public function stopInstance(string $vmid, ?string $node = null): void
    {
        if (isset(static::$vms[$vmid])) {
            static::$vms[$vmid]['status'] = 'stopped';
        }
    }

    public function rebootInstance(string $vmid, ?string $node = null): void
    {
        if (isset(static::$vms[$vmid])) {
            static::$vms[$vmid]['status'] = 'running';
        }
    }

    public function resizeInstance(string $vmid, ?string $node, int $cpu, int $memory, int $disk): void
    {
        if (isset(static::$vms[$vmid])) {
            static::$vms[$vmid]['cpu'] = $cpu;
            static::$vms[$vmid]['memory'] = $memory;
            static::$vms[$vmid]['disk'] = $disk;
        }
    }

    public function getInstanceStatus(string $vmid, ?string $node = null): string
    {
        return static::$vms[$vmid]['status'] ?? 'stopped';
    }

    public function getMetrics(string $node): HostMetrics
    {
        $hash = crc32($node);

        return new HostMetrics(
            cpuUsage: (float) (10 + ($hash % 60)),
            memoryTotal: 262144,
            memoryUsed: (int) (60000 + ($hash % 120000)),
            diskTotal: 4194304,
            diskUsed: (int) (1000000 + ($hash % 2000000)),
            uptime: 86400 * 14,
        );
    }

    public function waitForTask(string $task, ?string $node = null, int $timeout = 300): void
    {
        // Demo tasks complete immediately.
    }

    public function createSnapshot(string $vmid, string $name, ?string $node = null): void
    {
    }

    public function listSnapshots(string $vmid, ?string $node = null): array
    {
        return ['demo-snapshot-1'];
    }

    public function deleteSnapshot(string $vmid, string $name, ?string $node = null): void
    {
    }

    public function rollbackSnapshot(string $vmid, string $name, ?string $node = null): void
    {
    }
}
