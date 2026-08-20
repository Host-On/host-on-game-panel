<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $hostname
 * @property int|null $customer_id
 * @property int|null $provider_id
 * @property int|null $cluster_id
 * @property int|null $host_id
 * @property int|null $template_id
 * @property string|null $vmid
 * @property string $status
 * @property int $cpu
 * @property int $memory
 * @property int $disk
 * @property string|null $storage
 * @property string|null $bridge
 * @property string|null $management_ip
 * @property string|null $game_ip
 * @property int|null $ip_pool_id
 * @property int|null $wings_node_id
 * @property array|null $metadata
 */
class ComputeInstance extends Model
{
    public const RESOURCE_NAME = 'compute_instance';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CREATING = 'creating';
    public const STATUS_STARTING = 'starting';
    public const STATUS_RUNNING = 'running';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATING = 'terminating';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_FAILED = 'failed';

    protected $table = 'compute_instances';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'cpu' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'metadata' => 'array',
        'provisioned_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'provider_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(InfrastructureCluster::class, 'cluster_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(InfrastructureHost::class, 'host_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InfrastructureTemplate::class, 'template_id');
    }

    public function ipPool(): BelongsTo
    {
        return $this->belongsTo(InfrastructureIpPool::class, 'ip_pool_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'wings_node_id');
    }

    public function gameServices(): HasMany
    {
        return $this->hasMany(GameService::class, 'compute_instance_id');
    }
}
