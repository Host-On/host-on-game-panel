<?php

namespace Pterodactyl\Services\Infrastructure\Objects;

/**
 * Current utilization metrics for a compute node.
 *
 * Memory and disk values are expressed in megabytes (MiB) to stay consistent
 * with the rest of the Panel.
 */
class HostMetrics
{
    public function __construct(
        public float $cpuUsage = 0.0,
        public int $memoryTotal = 0,
        public int $memoryUsed = 0,
        public int $diskTotal = 0,
        public int $diskUsed = 0,
        public int $uptime = 0,
    ) {
    }

    public function getMemoryFree(): int
    {
        return max(0, $this->memoryTotal - $this->memoryUsed);
    }

    public function getDiskFree(): int
    {
        return max(0, $this->diskTotal - $this->diskUsed);
    }

    public function getMemoryUtilization(): float
    {
        return $this->memoryTotal > 0 ? ($this->memoryUsed / $this->memoryTotal) * 100 : 0;
    }

    public function getDiskUtilization(): float
    {
        return $this->diskTotal > 0 ? ($this->diskUsed / $this->diskTotal) * 100 : 0;
    }

    public function toArray(): array
    {
        return [
            'cpu_usage' => $this->cpuUsage,
            'memory_total' => $this->memoryTotal,
            'memory_used' => $this->memoryUsed,
            'memory_free' => $this->getMemoryFree(),
            'disk_total' => $this->diskTotal,
            'disk_used' => $this->diskUsed,
            'disk_free' => $this->getDiskFree(),
            'uptime' => $this->uptime,
        ];
    }
}
