<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $type
 * @property string|null $api_url
 * @property string|null $auth_user
 * @property string|null $auth_token
 * @property bool $tls_verify
 * @property string|null $tls_fingerprint
 * @property int $timeout
 * @property int|null $location_id
 * @property bool $enabled
 * @property bool $maintenance_mode
 * @property string|null $status
 */
class InfrastructureProvider extends Model
{
    public const RESOURCE_NAME = 'infrastructure_provider';

    public const TYPE_PROXMOX = 'proxmox';
    public const TYPE_FAKE = 'fake';

    protected $table = 'infrastructure_providers';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'tls_verify' => 'boolean',
        'enabled' => 'boolean',
        'maintenance_mode' => 'boolean',
        'timeout' => 'integer',
        'location_id' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'type' => 'required|in:proxmox,fake',
        'api_url' => 'nullable|string|max:191',
        'auth_user' => 'nullable|string|max:191',
        'auth_token' => 'nullable|string',
        'tls_fingerprint' => 'nullable|string|max:191',
    ];

    public function clusters(): HasMany
    {
        return $this->hasMany(InfrastructureCluster::class, 'provider_id');
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(InfrastructureHost::class, 'provider_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
