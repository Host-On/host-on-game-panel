<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $token_hash
 * @property int|null $provisioning_job_id
 * @property int|null $compute_instance_id
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $used_at
 * @property bool $revoked
 */
class BootstrapToken extends Model
{
    public const RESOURCE_NAME = 'bootstrap_token';

    protected $table = 'bootstrap_tokens';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public static array $validationRules = [
        'token_hash' => 'required|string|max:64',
        'expires_at' => 'required',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProvisioningJob::class, 'provisioning_job_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return !$this->revoked && $this->used_at === null && !$this->isExpired();
    }
}
