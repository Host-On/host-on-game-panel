<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Proxmox cluster: the top-level infrastructure unit an administrator
 * manages. It carries its own connection configuration and synchronizes its
 * hypervisor hosts from the Proxmox API.
 *
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
class InfrastructureCluster extends Model
{
    public const RESOURCE_NAME = 'infrastructure_cluster';

    public const TYPE_PROXMOX = 'proxmox';
    public const TYPE_FAKE = 'fake';

    protected $table = 'infrastructure_clusters';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The connection token is stored encrypted and must never be serialized.
     */
    protected $hidden = ['auth_token'];

    protected $casts = [
        'enabled' => 'boolean',
        'maintenance_mode' => 'boolean',
        'tls_verify' => 'boolean',
        'timeout' => 'integer',
        'metadata' => 'array',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(InfrastructureHost::class, 'cluster_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(InfrastructureTemplate::class, 'cluster_id');
    }
}
