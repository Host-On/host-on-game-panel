<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $game_catalog_id
 * @property int|null $nest_id
 * @property int|null $egg_id
 * @property int $cpu
 * @property int $memory
 * @property int $disk
 * @property int $game_cpu
 * @property int $game_memory
 * @property int $game_disk
 * @property int $system_reserve
 * @property array|null $ports
 * @property int $backups
 * @property string $infrastructure_type
 * @property int|null $template_id
 * @property int|null $location_id
 * @property string|null $price
 * @property bool $enabled
 */
class ResourceProfile extends Model
{
    public const RESOURCE_NAME = 'resource_profile';

    public const INFRA_DEDICATED_VM = 'dedicated_vm';
    public const INFRA_STATIC = 'static';
    public const INFRA_SHARED = 'shared';

    protected $table = 'resource_profiles';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'ports' => 'array',
        'cpu' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'game_cpu' => 'integer',
        'game_memory' => 'integer',
        'game_disk' => 'integer',
        'system_reserve' => 'integer',
        'backups' => 'integer',
        'enabled' => 'boolean',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'slug' => 'required|string|between:1,191|unique:resource_profiles,slug',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(GameCatalogEntry::class, 'game_catalog_id');
    }

    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InfrastructureTemplate::class, 'template_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
