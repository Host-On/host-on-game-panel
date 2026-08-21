<?php

namespace Pterodactyl\Services\Infrastructure\Proxmox;

use Pterodactyl\Services\Infrastructure\Objects\HostMetrics;
use Pterodactyl\Services\Infrastructure\Objects\HostSummary;
use Pterodactyl\Services\Infrastructure\Objects\InstanceResult;
use Pterodactyl\Services\Infrastructure\Objects\InstanceSpec;
use Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;

/**
 * Native Proxmox VE provider implementation.
 *
 * This implementation talks to the Proxmox REST API directly. It never shells
 * out to `qm` or any other local binary. It is intended to be used with QEMU/KVM
 * guests cloned from Cloud-Init enabled templates.
 */
class ProxmoxInfrastructureProvider implements InfrastructureProviderInterface
{
    public function __construct(protected ProxmoxClient $client)
    {
    }

    public function testConnection(): void
    {
        // Any authenticated call that requires at least read access verifies the
        // connection. The /nodes endpoint requires Sys.Audit / permissions that
        // any scoped token should have.
        $this->client->get('nodes');
    }

    public function getNodes(): array
    {
        $data = $this->client->get('nodes');

        $nodes = [];
        foreach ($data['data'] ?? [] as $node) {
            $nodes[] = new HostSummary(
                name: (string) ($node['node'] ?? ''),
                status: (string) ($node['status'] ?? 'unknown'),
                cpu: (int) ($node['maxcpu'] ?? 0),
                maxMemory: (int) round(((int) ($node['maxmem'] ?? 0)) / 1024 / 1024),
                maxDisk: (int) round(((int) ($node['maxdisk'] ?? 0)) / 1024 / 1024 / 1024),
            );
        }

        return $nodes;
    }

    public function getNextVmId(): int
    {
        $data = $this->client->get('cluster/nextid');

        return (int) ($data['data'] ?? 0);
    }

    public function createInstance(InstanceSpec $spec): InstanceResult
    {
        $node = $spec->templateNode ?? $this->firstOnlineNode();

        if (empty($spec->templateVmid)) {
            throw new InfrastructureException('No template VMID was provided for the clone.');
        }

        // Reserve the next free VMID up front and use the SAME id for the
        // clone request and every subsequent operation. Never derive the id
        // from a second API call — that could point at a different VM.
        $vmid = $this->getNextVmId();
        if ($vmid <= 0) {
            throw new InfrastructureException('Proxmox did not return a usable next free VMID.');
        }

        $response = $this->client->post(sprintf('nodes/%s/qemu/%d/clone', $node, $spec->templateVmid), [
            'newid' => $vmid,
            'name' => $spec->name,
            'full' => $spec->fullClone ? 1 : 0,
            'target' => $node,
            'storage' => $spec->storage,
        ]);

        $task = (string) ($response['data'] ?? '');

        // Apply the desired hardware configuration and Cloud-Init settings.
        $this->configure((string) $vmid, $node, $spec);

        return new InstanceResult(vmid: (string) $vmid, task: $task, node: $node);
    }

    /**
     * Configure a freshly cloned instance (name, CPU, memory, network, disk).
     */
    protected function configure(string $vmid, string $node, InstanceSpec $spec): void
    {
        $config = [
            'name' => $spec->name,
            'hostname' => $spec->hostname,
            'cores' => $spec->cpu,
            'sockets' => 1,
            'memory' => $spec->memory,
        ];

        if (!empty($spec->bridge)) {
            $config['net0'] = sprintf('virtio,bridge=%s', $spec->bridge);
        }

        $this->client->put(sprintf('nodes/%s/qemu/%s/config', $node, $vmid), $config);

        // Resize the root disk of the NEWLY cloned VM only. The disk name is
        // discovered from the VM config so templates with any disk layout
        // work; this never touches any other VM.
        if ($spec->disk > 0) {
            $disk = $this->findRootDisk($vmid, $node);
            if ($disk !== null) {
                $this->client->put(sprintf('nodes/%s/qemu/%s/resize', $node, $vmid), [
                    'disk' => $disk,
                    'size' => sprintf('%dG', $spec->disk),
                ]);
            }
        }

        if (!empty($spec->cloudInit)) {
            $this->client->put(sprintf('nodes/%s/qemu/%s/config', $node, $vmid), $spec->cloudInit);
        }
    }

    /**
     * Find the first disk device (e.g. scsi0/virtio0/sata0/ide0) of a VM.
     */
    protected function findRootDisk(string $vmid, string $node): ?string
    {
        $data = $this->client->get(sprintf('nodes/%s/qemu/%s/config', $node, $vmid));

        foreach (array_keys($data['data'] ?? []) as $key) {
            if (preg_match('/^(scsi|virtio|sata|ide)\d+$/', (string) $key) === 1) {
                return (string) $key;
            }
        }

        return null;
    }

    public function deleteInstance(string $vmid, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->delete(sprintf('nodes/%s/qemu/%s', $node, $vmid), ['purge' => 1]);
    }

    public function startInstance(string $vmid, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->post(sprintf('nodes/%s/qemu/%s/status/start', $node, $vmid));
    }

    public function stopInstance(string $vmid, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->post(sprintf('nodes/%s/qemu/%s/status/shutdown', $node, $vmid));
    }

    public function rebootInstance(string $vmid, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->post(sprintf('nodes/%s/qemu/%s/status/reboot', $node, $vmid));
    }

    public function resizeInstance(string $vmid, ?string $node, int $cpu, int $memory, int $disk): void
    {
        $node = $node ?? $this->locateNode($vmid);

        $this->client->put(sprintf('nodes/%s/qemu/%s/config', $node, $vmid), [
            'cores' => $cpu,
            'memory' => $memory,
        ]);

        if ($disk > 0) {
            $rootDisk = $this->findRootDisk($vmid, $node);
            if ($rootDisk !== null) {
                $this->client->put(sprintf('nodes/%s/qemu/%s/resize', $node, $vmid), [
                    'disk' => $rootDisk,
                    'size' => sprintf('%dG', $disk),
                ]);
            }
        }
    }

    public function getInstanceStatus(string $vmid, ?string $node = null): string
    {
        $node = $node ?? $this->locateNode($vmid);
        $data = $this->client->get(sprintf('nodes/%s/qemu/%s/status/current', $node, $vmid));

        return (string) ($data['data']['status'] ?? 'unknown');
    }

    public function getMetrics(string $node): HostMetrics
    {
        $data = $this->client->get(sprintf('nodes/%s/status', $node));

        $cpu = (float) ($data['data']['cpu'] ?? 0);
        $memTotal = (int) ($data['data']['memory']['total'] ?? 0);
        $memUsed = (int) ($data['data']['memory']['used'] ?? 0);
        $diskTotal = (int) ($data['data']['rootfs']['total'] ?? 0);
        $diskUsed = (int) ($data['data']['rootfs']['used'] ?? 0);
        $uptime = (int) ($data['data']['uptime'] ?? 0);

        return new HostMetrics(
            cpuUsage: $cpu,
            memoryTotal: (int) round($memTotal / 1024 / 1024),
            memoryUsed: (int) round($memUsed / 1024 / 1024),
            diskTotal: (int) round($diskTotal / 1024 / 1024),
            diskUsed: (int) round($diskUsed / 1024 / 1024),
            uptime: $uptime,
        );
    }

    public function waitForTask(string $task, ?string $node = null, int $timeout = 300): void
    {
        $node = $node ?? $this->firstOnlineNode();
        $start = time();

        while (time() - $start < $timeout) {
            $data = $this->client->get(sprintf('nodes/%s/tasks/%s/status', $node, urlencode($task)));

            $status = (string) ($data['data']['status'] ?? 'running');

            if ($status === 'stopped') {
                $exit = (string) ($data['data']['exitstatus'] ?? 'unknown');

                if ($exit !== 'OK') {
                    throw new InfrastructureException(sprintf('Proxmox task %s finished with status "%s".', $task, $exit));
                }

                return;
            }

            if (in_array($status, ['error', 'aborted'], true)) {
                throw new InfrastructureException(sprintf('Proxmox task %s ended with status "%s".', $task, $status));
            }

            sleep(2);
        }

        throw new InfrastructureException(sprintf('Proxmox task %s timed out after %d seconds.', $task, $timeout));
    }

    public function createSnapshot(string $vmid, string $name, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->post(sprintf('nodes/%s/qemu/%s/snapshot', $node, $vmid), ['snapname' => $name]);
    }

    public function listSnapshots(string $vmid, ?string $node = null): array
    {
        $node = $node ?? $this->locateNode($vmid);
        $data = $this->client->get(sprintf('nodes/%s/qemu/%s/snapshot', $node, $vmid));

        return array_map(fn ($snap) => (string) $snap['name'], $data['data'] ?? []);
    }

    public function deleteSnapshot(string $vmid, string $name, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->delete(sprintf('nodes/%s/qemu/%s/snapshot/%s', $node, $vmid, $name));
    }

    public function rollbackSnapshot(string $vmid, string $name, ?string $node = null): void
    {
        $node = $node ?? $this->locateNode($vmid);
        $this->client->post(sprintf('nodes/%s/qemu/%s/snapshot/%s/rollback', $node, $vmid, $name));
    }

    /**
     * Locate the node a VM currently lives on by scanning cluster resources.
     */
    protected function locateNode(string $vmid): string
    {
        $data = $this->client->get('cluster/resources', ['type' => 'vm']);

        foreach ($data['data'] ?? [] as $resource) {
            if ((string) ($resource['vmid'] ?? '') === $vmid) {
                return (string) $resource['node'];
            }
        }

        return $this->firstOnlineNode();
    }

    protected function firstOnlineNode(): string
    {
        foreach ($this->getNodes() as $node) {
            if ($node->status === 'online') {
                return $node->name;
            }
        }

        throw new InfrastructureException('No online Proxmox nodes are available.');
    }
}
