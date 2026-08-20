<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $game_license_pool_id
 * @property string $license_key
 * @property string $status
 * @property int|null $game_service_id
 * @property int|null $compute_instance_id
 * @property \Carbon\Carbon|null $allocated_at
 * @property \Carbon\Carbon|null $released_at
 */
class GameLicense extends Model
{
    public const RESOURCE_NAME = 'game_license';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ALLOCATED = 'allocated';
    public const STATUS_REVOKED = 'revoked';

    protected $table = 'game_licenses';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The license key is stored encrypted and must never be serialized to the
     * frontend. It is excluded from all JSON/array representations.
     */
    protected $hidden = ['license_key'];

    protected $casts = [
        'allocated_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public static array $validationRules = [
        'game_license_pool_id' => 'required|exists:game_license_pools,id',
        'license_key' => 'required|string',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(GameLicensePool::class, 'game_license_pool_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'game_service_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }
}
