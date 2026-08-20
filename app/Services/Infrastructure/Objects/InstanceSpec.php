<?php

namespace Pterodactyl\Services\Infrastructure\Objects;

/**
 * Describes the target configuration for a managed compute instance that
 * should be created on the infrastructure provider.
 */
class InstanceSpec
{
    public function __construct(
        public string $name,
        public string $hostname,
        public int $cpu,
        public int $memory,
        public int $disk,
        public ?string $storage = null,
        public ?string $bridge = null,
        public ?int $templateVmid = null,
        public ?string $templateNode = null,
        public bool $fullClone = false,
        public ?array $cloudInit = null,
        public array $tags = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hostname' => $this->hostname,
            'cpu' => $this->cpu,
            'memory' => $this->memory,
            'disk' => $this->disk,
            'storage' => $this->storage,
            'bridge' => $this->bridge,
            'template_vmid' => $this->templateVmid,
            'template_node' => $this->templateNode,
            'full_clone' => $this->fullClone,
            'cloud_init' => $this->cloudInit,
            'tags' => $this->tags,
        ];
    }
}
