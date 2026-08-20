<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $provisioning_job_id
 * @property string $step
 * @property string $status
 * @property string|null $message
 * @property string|null $external_id
 * @property int $attempt
 */
class ProvisioningStep extends Model
{
    public const RESOURCE_NAME = 'provisioning_step';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'provisioning_steps';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'attempt' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static array $validationRules = [
        'provisioning_job_id' => 'required|exists:provisioning_jobs,id',
        'step' => 'required|string|max:64',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProvisioningJob::class, 'provisioning_job_id');
    }
}
