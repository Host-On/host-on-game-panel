<?php

namespace Pterodactyl\Services\Infrastructure\Objects;

/**
 * A lightweight summary of a compute node (hypervisor) as reported by the
 * infrastructure provider.
 */
class HostSummary
{
    public function __construct(
        public string $name,
        public string $status = 'online',
        public int $cpu = 0,
        public int $maxMemory = 0,
        public int $maxDisk = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'cpu' => $this->cpu,
            'max_memory' => $this->maxMemory,
            'max_disk' => $this->maxDisk,
        ];
    }
}
