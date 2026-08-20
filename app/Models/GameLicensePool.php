<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property int|null $game_catalog_id
 * @property string $provider
 * @property string $license_type
 * @property string $license_variable
 * @property bool $enabled
 */
class GameLicensePool extends Model
{
    public const RESOURCE_NAME = 'game_license_pool';

    protected $table = 'game_license_pools';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'slug' => 'required|string|between:1,191|unique:game_license_pools,slug',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(GameCatalogEntry::class, 'game_catalog_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(GameLicense::class, 'game_license_pool_id');
    }
}
