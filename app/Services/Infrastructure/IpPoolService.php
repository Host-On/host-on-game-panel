<?php

namespace Pterodactyl\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\InfrastructureIpAllocation;
use Pterodactyl\Models\InfrastructureIpPool;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;

/**
 * Manages individual IP addresses within IP pools.
 *
 * Public IPs are pre-released ("freigegeben") into a pool, then allocated to
 * customer VMs during provisioning and passed to Proxmox via Cloud-Init so
 * every VM gets its own dedicated public IP. On termination the IP is
 * released back into the pool.
 */
class IpPoolService
{
    /**
     * Maximum number of addresses we will auto-generate from a range in a
     * single sync, to avoid accidentally materializing huge ranges.
     */
    protected const MAX_SYNC_ADDRESSES = 1024;

    /**
     * Generate (release) all addresses between a pool's allocation_start and
     * allocation_end. Returns the number of newly created addresses.
     */
    public function sync(InfrastructureIpPool $pool): int
    {
        $start = $pool->allocation_start;
        $end = $pool->allocation_end;

        if (empty($start)) {
            return 0;
        }

        if (empty($end)) {
            $end = $start;
        }

        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong === false || $endLong === false || $endLong < $startLong) {
            throw new InfrastructureException('Invalid IP allocation range on pool "' . $pool->name . '".');
        }

        $count = $endLong - $startLong + 1;
        if ($count > self::MAX_SYNC_ADDRESSES) {
            throw new InfrastructureException(sprintf('IP pool "%s" range is too large (%d addresses). Reduce the range or split it into multiple pools.', $pool->name, $count));
        }

        $existing = InfrastructureIpAllocation::query()
            ->where('ip_pool_id', $pool->id)
            ->pluck('address')
            ->flip();

        $created = 0;
        for ($i = $startLong; $i <= $endLong; $i++) {
            $address = long2ip($i);

            if (isset($existing[$address])) {
                continue;
            }

            InfrastructureIpAllocation::query()->create([
                'uuid' => Str::uuid()->toString(),
                'ip_pool_id' => $pool->id,
                'address' => $address,
                'status' => InfrastructureIpAllocation::STATUS_AVAILABLE,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Allocate the first available address from a pool, optionally assigning
     * it to a compute instance.
     *
     * @throws InfrastructureException
     */
    public function allocate(InfrastructureIpPool $pool, ?ComputeInstance $instance = null): InfrastructureIpAllocation
    {
        /** @var InfrastructureIpAllocation|null $allocation */
        $allocation = InfrastructureIpAllocation::query()
            ->where('ip_pool_id', $pool->id)
            ->where('status', InfrastructureIpAllocation::STATUS_AVAILABLE)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (!$allocation) {
            throw new InfrastructureException(sprintf('No free public IP addresses are available in pool "%s". Release addresses or add a new pool.', $pool->name));
        }

        $allocation->update([
            'status' => InfrastructureIpAllocation::STATUS_ALLOCATED,
            'compute_instance_id' => $instance?->id,
            'allocated_at' => now(),
            'released_at' => null,
        ]);

        return $allocation;
    }

    /**
     * Release all addresses assigned to a compute instance.
     */
    public function releaseForInstance(ComputeInstance $instance): void
    {
        InfrastructureIpAllocation::query()
            ->where('compute_instance_id', $instance->id)
            ->update([
                'status' => InfrastructureIpAllocation::STATUS_AVAILABLE,
                'compute_instance_id' => null,
                'allocated_at' => null,
                'released_at' => now(),
            ]);
    }

    /**
     * Manually add a single address to a pool (released/available).
     */
    public function addAddress(InfrastructureIpPool $pool, string $address): InfrastructureIpAllocation
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InfrastructureException('"' . $address . '" is not a valid IPv4 address.');
        }

        return InfrastructureIpAllocation::query()->firstOrCreate(
            ['ip_pool_id' => $pool->id, 'address' => $address],
            ['uuid' => Str::uuid()->toString(), 'status' => InfrastructureIpAllocation::STATUS_AVAILABLE]
        );
    }

    /**
     * Release a specific address (make it available again), detaching any
     * compute instance it was assigned to.
     */
    public function release(InfrastructureIpAllocation $allocation): void
    {
        $allocation->update([
            'status' => InfrastructureIpAllocation::STATUS_AVAILABLE,
            'compute_instance_id' => null,
            'allocated_at' => null,
            'released_at' => now(),
        ]);
    }

    /**
     * Reserve a specific address so it is not handed out automatically.
     */
    public function reserve(InfrastructureIpAllocation $allocation): void
    {
        $allocation->update(['status' => InfrastructureIpAllocation::STATUS_RESERVED]);
    }

    /**
     * Derive the dotted-quad netmask from a CIDR network string.
     */
    public function netmaskFromCidr(string $cidr): string
    {
        $prefix = $this->prefixFromCidr($cidr);

        return long2ip(~((1 << (32 - $prefix)) - 1) & 0xFFFFFFFF);
    }

    /**
     * Derive the prefix length (e.g. 24) from a CIDR network string.
     */
    public function prefixFromCidr(string $cidr): int
    {
        if (str_contains($cidr, '/')) {
            [, $prefix] = explode('/', $cidr);

            return (int) $prefix;
        }

        return 24;
    }

    /**
     * Derive the gateway from a CIDR network string (network address + 1).
     */
    public function defaultGatewayFromCidr(string $cidr): string
    {
        if (str_contains($cidr, '/')) {
            [$network] = explode('/', $cidr);
            $long = ip2long($network);

            return long2ip($long + 1);
        }

        return '';
    }
}
