<?php

namespace Pterodactyl\Services\Infrastructure\Objects;

/**
 * Result returned after an instance is created on the provider.
 */
class InstanceResult
{
    public function __construct(
        public string $vmid,
        public ?string $task = null,
        public ?string $node = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'vmid' => $this->vmid,
            'task' => $this->task,
            'node' => $this->node,
        ];
    }
}
