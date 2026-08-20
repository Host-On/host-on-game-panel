<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $provider_id
 * @property int|null $location_id
 * @property bool $enabled
 * @property bool $maintenance_mode
 * @property array|null $metadata
 */
class InfrastructureCluster extends Model
{
    public const RESOURCE_NAME = 'infrastructure_cluster';

    protected $table = 'infrastructure_clusters';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'enabled' => 'boolean',
        'maintenance_mode' => 'boolean',
        'metadata' => 'array',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'provider_id' => 'required|exists:infrastructure_providers,id',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'provider_id');
    }

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
