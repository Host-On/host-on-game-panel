<?php

namespace Pterodactyl\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\BootstrapToken;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\ProvisioningJob;

/**
 * Issues and validates one-time, expiring bootstrap tokens used to securely
 * bootstrap a freshly provisioned customer VM.
 *
 * Tokens are hashed before storage, expire automatically, can only be used
 * once, are scoped to a single provisioning job/instance and never grant
 * administrative access.
 */
class BootstrapTokenService
{
    /**
     * Issue a new one-time bootstrap token.
     *
     * @return array{token: string, model: BootstrapToken}
     */
    public function issue(ProvisioningJob $job, ?ComputeInstance $instance = null, int $ttlMinutes = 60): array
    {
        $plain = Str::random(64);

        $model = BootstrapToken::query()->create([
            'uuid' => Str::uuid()->toString(),
            'token_hash' => hash('sha256', $plain),
            'provisioning_job_id' => $job->id,
            'compute_instance_id' => $instance?->id,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return ['token' => $plain, 'model' => $model];
    }

    /**
     * Validate a bootstrap token and mark it as used. Returns the associated
     * provisioning job when valid.
     *
     * @throws \Pterodactyl\Exceptions\Infrastructure\InfrastructureException
     */
    public function consume(string $plain): ProvisioningJob
    {
        $hash = hash('sha256', $plain);

        $token = BootstrapToken::query()->where('token_hash', $hash)->first();

        if (!$token || !$token->isUsable()) {
            throw new \Pterodactyl\Exceptions\Infrastructure\InfrastructureException('Invalid, expired or already used bootstrap token.');
        }

        if (!$token->job) {
            throw new \Pterodactyl\Exceptions\Infrastructure\InfrastructureException('Bootstrap token is not associated with a provisioning job.');
        }

        $token->update(['used_at' => now()]);

        return $token->job;
    }

    /**
     * Revoke a bootstrap token by its plain value.
     */
    public function revoke(string $plain): void
    {
        BootstrapToken::query()->where('token_hash', hash('sha256', $plain))->update(['revoked' => true]);
    }
}
