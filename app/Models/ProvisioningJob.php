<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $external_id
 * @property int|null $user_id
 * @property int|null $game_service_id
 * @property int|null $resource_profile_id
 * @property int|null $location_id
 * @property int|null $provider_id
 * @property int|null $cluster_id
 * @property int|null $host_id
 * @property int|null $template_id
 * @property string|null $vmid
 * @property int|null $compute_instance_id
 * @property int|null $wings_node_id
 * @property int|null $server_id
 * @property string $status
 * @property string|null $current_step
 * @property int $attempt_count
 * @property string|null $error
 * @property string|null $rollback_state
 */
class ProvisioningJob extends Model
{
    public const RESOURCE_NAME = 'provisioning_job';

    // High-level job statuses.
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLING_BACK = 'rolling_back';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_SUSPENDED = 'suspended';

    // Provisioning steps, in order.
    public const STEP_SELECTING_HOST = 'selecting_host';
    public const STEP_CREATING_VM = 'creating_vm';
    public const STEP_CONFIGURING_VM = 'configuring_vm';
    public const STEP_STARTING_VM = 'starting_vm';
    public const STEP_WAITING_FOR_VM = 'waiting_for_vm';
    public const STEP_BOOTSTRAPPING = 'bootstrapping';
    public const STEP_INSTALLING_WINGS = 'installing_wings';
    public const STEP_REGISTERING_NODE = 'registering_node';
    public const STEP_CONFIGURING_NETWORK = 'configuring_network';
    public const STEP_CREATING_ALLOCATIONS = 'creating_allocations';
    public const STEP_CREATING_GAME_SERVER = 'creating_game_server';
    public const STEP_INSTALLING_GAME = 'installing_game';
    public const STEP_STARTING_GAME = 'starting_game';
    public const STEP_VERIFYING = 'verifying';

    public const STEPS = [
        self::STEP_SELECTING_HOST,
        self::STEP_CREATING_VM,
        self::STEP_CONFIGURING_VM,
        self::STEP_STARTING_VM,
        self::STEP_WAITING_FOR_VM,
        self::STEP_CONFIGURING_NETWORK,
        self::STEP_REGISTERING_NODE,
        self::STEP_BOOTSTRAPPING,
        self::STEP_INSTALLING_WINGS,
        self::STEP_CREATING_ALLOCATIONS,
        self::STEP_CREATING_GAME_SERVER,
        self::STEP_INSTALLING_GAME,
        self::STEP_STARTING_GAME,
        self::STEP_VERIFYING,
    ];

    protected $table = 'provisioning_jobs';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'attempt_count' => 'integer',
        'provisioned_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public static array $validationRules = [
        'status' => 'required|string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'game_service_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ResourceProfile::class, 'resource_profile_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'provider_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(InfrastructureCluster::class, 'cluster_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(InfrastructureHost::class, 'host_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InfrastructureTemplate::class, 'template_id');
    }

    public function computeInstance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'wings_node_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProvisioningStep::class, 'provisioning_job_id')->orderBy('id');
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
