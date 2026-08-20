<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $external_id
 * @property int $user_id
 * @property string $name
 * @property int|null $compute_instance_id
 * @property int|null $server_id
 * @property int|null $resource_profile_id
 * @property int|null $game_catalog_id
 * @property int|null $location_id
 * @property string $status
 * @property array|null $configuration
 */
class GameService extends Model
{
    public const RESOURCE_NAME = 'game_service';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATING = 'terminating';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_FAILED = 'failed';

    protected $table = 'game_services';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'configuration' => 'array',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'user_id' => 'required|exists:users,id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function computeInstance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ResourceProfile::class, 'resource_profile_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(GameCatalogEntry::class, 'game_catalog_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function provisioningJobs(): HasMany
    {
        return $this->hasMany(ProvisioningJob::class, 'game_service_id');
    }
}
