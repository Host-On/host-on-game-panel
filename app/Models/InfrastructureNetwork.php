<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $bridge
 * @property int|null $vlan
 * @property string|null $gateway
 * @property string|null $subnet
 * @property string|null $dns
 * @property int|null $location_id
 * @property bool $enabled
 */
class InfrastructureNetwork extends Model
{
    public const RESOURCE_NAME = 'infrastructure_network';

    protected $table = 'infrastructure_networks';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'vlan' => 'integer',
        'enabled' => 'boolean',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'bridge' => 'required|string|max:191',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
