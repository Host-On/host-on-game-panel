<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int|null $cluster_id
 * @property int $template_vmid
 * @property string $storage
 * @property string $bridge
 * @property bool $cloud_init_enabled
 * @property bool $wings_bootstrap_enabled
 * @property int $default_cpu
 * @property int $default_memory
 * @property int $default_disk
 * @property bool $enabled
 */
class InfrastructureTemplate extends Model
{
    public const RESOURCE_NAME = 'infrastructure_template';

    protected $table = 'infrastructure_templates';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'template_vmid' => 'integer',
        'default_cpu' => 'integer',
        'default_memory' => 'integer',
        'default_disk' => 'integer',
        'cloud_init_enabled' => 'boolean',
        'wings_bootstrap_enabled' => 'boolean',
        'enabled' => 'boolean',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'cluster_id' => 'required|exists:infrastructure_clusters,id',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(InfrastructureCluster::class, 'cluster_id');
    }
}
