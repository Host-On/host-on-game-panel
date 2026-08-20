<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $ip_pool_id
 * @property string $address
 * @property string $status
 * @property int|null $compute_instance_id
 * @property \Carbon\Carbon|null $allocated_at
 * @property \Carbon\Carbon|null $released_at
 */
class InfrastructureIpAllocation extends Model
{
    public const RESOURCE_NAME = 'infrastructure_ip_allocation';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ALLOCATED = 'allocated';
    public const STATUS_RESERVED = 'reserved';

    protected $table = 'infrastructure_ip_allocations';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'allocated_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public static array $validationRules = [
        'ip_pool_id' => 'required|exists:infrastructure_ip_pools,id',
        'address' => 'required|ip',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(InfrastructureIpPool::class, 'ip_pool_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }
}
