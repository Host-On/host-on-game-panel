<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $artwork
 * @property int|null $nest_id
 * @property int|null $egg_id
 * @property string|null $default_image
 * @property int $min_ram
 * @property int $recommended_ram
 * @property array|null $ports
 * @property array|null $environment
 * @property bool $enabled
 * @property int $sort_order
 */
class GameCatalogEntry extends Model
{
    public const RESOURCE_NAME = 'game_catalog_entry';

    protected $table = 'game_catalog_entries';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'ports' => 'array',
        'environment' => 'array',
        'enabled' => 'boolean',
        'requires_license' => 'boolean',
        'sort_order' => 'integer',
        'min_ram' => 'integer',
        'recommended_ram' => 'integer',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'slug' => 'required|string|between:1,191|unique:game_catalog_entries,slug',
    ];

    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(ResourceProfile::class, 'game_catalog_id');
    }
}
