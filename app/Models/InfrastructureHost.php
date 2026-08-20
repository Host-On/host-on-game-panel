<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $hostname
 * @property int|null $cluster_id
 * @property int|null $location_id
 * @property int $max_memory
 * @property int $max_disk
 * @property int $cpu_cores
 * @property int $allocated_memory
 * @property int $allocated_disk
 * @property float $cpu_utilization
 * @property float $memory_utilization
 * @property float $disk_utilization
 * @property string|null $external_id
 * @property bool $enabled
 * @property bool $maintenance_mode
 * @property array|null $metadata
 */
class InfrastructureHost extends Model
{
    public const RESOURCE_NAME = 'infrastructure_host';

    protected $table = 'infrastructure_hosts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'max_memory' => 'integer',
        'max_disk' => 'integer',
        'cpu_cores' => 'integer',
        'allocated_memory' => 'integer',
        'allocated_disk' => 'integer',
        'placement_weight' => 'integer',
        'reserved_memory' => 'integer',
        'reserved_disk' => 'integer',
        'cpu_utilization' => 'float',
        'memory_utilization' => 'float',
        'disk_utilization' => 'float',
        'enabled' => 'boolean',
        'maintenance_mode' => 'boolean',
        'allowed_product_classes' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'cluster_id' => 'required|exists:infrastructure_clusters,id',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(InfrastructureCluster::class, 'cluster_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isUnderMaintenance(): bool
    {
        return $this->maintenance_mode;
    }
}
