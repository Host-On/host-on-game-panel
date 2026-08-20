<?php

namespace Pterodactyl\Contracts\Infrastructure;

use Pterodactyl\Services\Infrastructure\Objects\InstanceResult;
use Pterodactyl\Services\Infrastructure\Objects\InstanceSpec;
use Pterodactyl\Services\Infrastructure\Objects\HostMetrics;
use Pterodactyl\Services\Infrastructure\Objects\HostSummary;

/**
 * Abstraction over the compute infrastructure layer that powers managed
 * Host-On Games customer VMs.
 *
 * Business logic MUST depend on this interface rather than on any concrete
 * provider (Proxmox, OpenStack, ...). Implementations map these primitives
 * onto a specific hypervisor/API.
 */
interface InfrastructureProviderInterface
{
    /**
     * Verify that the configured connection works and is authorized.
     *
     * @throws \Pterodactyl\Exceptions\Infrastructure\InfrastructureException
     */
    public function testConnection(): void;

    /**
     * Return the list of compute nodes (hypervisors) in this provider.
     *
     * @return HostSummary[]
     */
    public function getNodes(): array;

    /**
     * Return the next available VMID that can be used when provisioning.
     */
    public function getNextVmId(): int;

    /**
     * Create (clone) a new instance from a template.
     *
     * @throws \Pterodactyl\Exceptions\Infrastructure\InfrastructureException
     */
    public function createInstance(InstanceSpec $spec): InstanceResult;

    /**
     * Permanently delete an instance.
     */
    public function deleteInstance(string $vmid, ?string $node = null): void;

    /**
     * Start an instance.
     */
    public function startInstance(string $vmid, ?string $node = null): void;

    /**
     * Stop an instance (graceful shutdown).
     */
    public function stopInstance(string $vmid, ?string $node = null): void;

    /**
     * Reboot an instance.
     */
    public function rebootInstance(string $vmid, ?string $node = null): void;

    /**
     * Resize an instance's CPU, memory and disk.
     */
    public function resizeInstance(string $vmid, ?string $node, int $cpu, int $memory, int $disk): void;

    /**
     * Return the current status of an instance.
     */
    public function getInstanceStatus(string $vmid, ?string $node = null): string;

    /**
     * Return current utilization metrics for a specific compute node.
     */
    public function getMetrics(string $node): HostMetrics;

    /**
     * Block until the given remote task (e.g. a Proxmox UPID) reaches a terminal
     * state, or until the timeout elapses.
     *
     * @throws \Pterodactyl\Exceptions\Infrastructure\InfrastructureException
     */
    public function waitForTask(string $task, ?string $node = null, int $timeout = 300): void;

    /**
     * Create a snapshot of an instance.
     */
    public function createSnapshot(string $vmid, string $name, ?string $node = null): void;

    /**
     * List snapshot names for an instance.
     *
     * @return string[]
     */
    public function listSnapshots(string $vmid, ?string $node = null): array;

    /**
     * Delete a snapshot from an instance.
     */
    public function deleteSnapshot(string $vmid, string $name, ?string $node = null): void;

    /**
     * Roll an instance back to a previously created snapshot.
     */
    public function rollbackSnapshot(string $vmid, string $name, ?string $node = null): void;
}
