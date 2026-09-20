<?php

namespace Pterodactyl\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\InfrastructureAuditLog;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Central audit trail for infrastructure operations. Records who did what to
 * which resource, with before/after context where available. Secrets (API
 * tokens, license keys, bootstrap tokens) must never be passed in.
 */
class InfrastructureAuditService
{
    public function __construct(protected AuthFactory $auth)
    {
    }

    /**
     * Record an infrastructure operation.
     *
     * @param string $operation e.g. "vm.create", "cluster.sync", "service.suspend"
     * @param array $attributes contextual identifiers (provider_id, cluster_id,
     *                          host_id, compute_instance_id, node_id, server_id,
     *                          game_service_id, vmid, target_type, target_id)
     * @param array|null $before previous state (optional)
     * @param array|null $after new state (optional)
     * @param string $result "success" or "failure"
     * @param string|null $error sanitized error message on failure
     */
    public function record(
        string $operation,
        array $attributes = [],
        ?array $before = null,
        ?array $after = null,
        string $result = 'success',
        ?string $error = null,
    ): void {
        try {
            InfrastructureAuditLog::query()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $this->auth->guard()->user()?->id,
                'provider_id' => $attributes['provider_id'] ?? null,
                'cluster_id' => $attributes['cluster_id'] ?? null,
                'host_id' => $attributes['host_id'] ?? null,
                'compute_instance_id' => $attributes['compute_instance_id'] ?? null,
                'node_id' => $attributes['node_id'] ?? null,
                'server_id' => $attributes['server_id'] ?? null,
                'operation' => $operation,
                'target_type' => $attributes['target_type'] ?? null,
                'target_id' => $attributes['target_id'] ?? null,
                'before' => $before,
                'after' => $after,
                'vmid' => $attributes['vmid'] ?? null,
                'result' => $result,
                'error' => $error,
            ]);
        } catch (\Throwable $exception) {
            // Auditing must never break the operation itself.
            report($exception);
        }
    }

    /**
     * Record a failed operation.
     */
    public function failure(string $operation, array $attributes = [], ?string $error = null): void
    {
        $this->record($operation, $attributes, result: 'failure', error: $error);
    }
}
