<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $network
 * @property string|null $subnet
 * @property string|null $gateway
 * @property string $bridge
 * @property string|null $dns
 * @property string|null $allocation_start
 * @property string|null $allocation_end
 * @property int|null $location_id
 * @property bool $enabled
 */
class InfrastructureIpPool extends Model
{
    public const RESOURCE_NAME = 'infrastructure_ip_pool';

    protected $table = 'infrastructure_ip_pools';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'network' => 'required|string|max:191',
        'bridge' => 'required|string|max:191',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ComputeInstance::class, 'ip_pool_id');
    }
}
