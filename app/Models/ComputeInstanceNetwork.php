<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single network interface/address of a compute instance (customer VM).
 *
 * Management interfaces carry the internal Wings traffic; public interfaces
 * carry the dedicated public game IP allocated from an IP pool.
 *
 * @property int $id
 * @property string $uuid
 * @property int $compute_instance_id
 * @property string $type
 * @property string|null $ip
 * @property string|null $ipv6
 * @property int|null $vlan
 * @property string|null $network
 * @property string|null $gateway
 * @property string|null $bridge
 * @property int|null $ip_allocation_id
 */
class ComputeInstanceNetwork extends Model
{
    public const RESOURCE_NAME = 'compute_instance_network';

    public const TYPE_MANAGEMENT = 'management';
    public const TYPE_PUBLIC = 'public';

    protected $table = 'compute_instance_networks';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'vlan' => 'integer',
    ];

    public static array $validationRules = [
        'compute_instance_id' => 'required|exists:compute_instances,id',
        'type' => 'required|in:management,public',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InfrastructureIpAllocation::class, 'ip_allocation_id');
    }
}
