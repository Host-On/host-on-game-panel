<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int|null $provider_id
 * @property int|null $cluster_id
 * @property int|null $host_id
 * @property int|null $compute_instance_id
 * @property int|null $node_id
 * @property int|null $server_id
 * @property string $operation
 * @property string|null $target_type
 * @property string|null $target_id
 * @property array|null $before
 * @property array|null $after
 * @property string|null $vmid
 * @property string $result
 * @property string|null $error
 */
class InfrastructureAuditLog extends Model
{
    public const RESOURCE_NAME = 'infrastructure_audit_log';

    protected $table = 'infrastructure_audit_logs';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public static array $validationRules = [
        'operation' => 'required|string|max:64',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'provider_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(InfrastructureHost::class, 'host_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }
}
