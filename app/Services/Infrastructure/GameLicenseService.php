<?php

namespace Pterodactyl\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\GameLicense;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\GameLicensePool;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;

/**
 * Manages commercially licensed game keys.
 *
 * License keys are stored encrypted at rest and are only ever decrypted in
 * memory for the brief moment they are handed to the provisioning pipeline so
 * they can be injected into the game server's environment (which Wings passes
 * to the container). They are NEVER exposed to the customer or the frontend.
 *
 * Licenses must be supplied legitimately (e.g. by Host-On / GIANTS). This
 * abstraction provides no bypass of any licensing mechanism.
 */
class GameLicenseService
{
    public function __construct(protected Encrypter $encrypter)
    {
    }

    /**
     * Add a single license key to a pool (stored encrypted).
     */
    public function addLicense(GameLicensePool $pool, string $key): GameLicense
    {
        return GameLicense::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_license_pool_id' => $pool->id,
            'license_key' => $this->encrypter->encrypt(trim($key)),
            'status' => GameLicense::STATUS_AVAILABLE,
        ]);
    }

    /**
     * Bulk-add license keys to a pool. Returns the number of added licenses.
     */
    public function addLicenses(GameLicensePool $pool, array $keys): int
    {
        $added = 0;

        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $this->addLicense($pool, $key);
            $added++;
        }

        return $added;
    }

    /**
     * Allocate the first available license for a pool and return its plaintext
     * key. The caller is responsible for immediately injecting it into the
     * target environment and must not persist or log it.
     *
     * @throws InfrastructureException
     */
    public function allocate(GameLicensePool $pool, ?GameService $service = null, ?ComputeInstance $instance = null): string
    {
        /** @var GameLicense|null $license */
        $license = GameLicense::query()
            ->where('game_license_pool_id', $pool->id)
            ->where('status', GameLicense::STATUS_AVAILABLE)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (!$license) {
            throw new InfrastructureException(sprintf('No licenses are available in pool "%s". Add licenses before provisioning this game.', $pool->name));
        }

        $license->update([
            'status' => GameLicense::STATUS_ALLOCATED,
            'game_service_id' => $service?->id,
            'compute_instance_id' => $instance?->id,
            'allocated_at' => now(),
            'released_at' => null,
        ]);

        return $this->encrypter->decrypt($license->license_key);
    }

    /**
     * Release all licenses assigned to a game service.
     */
    public function releaseForService(GameService $service): void
    {
        GameLicense::query()
            ->where('game_service_id', $service->id)
            ->update([
                'status' => GameLicense::STATUS_AVAILABLE,
                'game_service_id' => null,
                'compute_instance_id' => null,
                'allocated_at' => null,
                'released_at' => now(),
            ]);
    }

    /**
     * Revoke a specific license.
     */
    public function revoke(GameLicense $license): void
    {
        $license->update([
            'status' => GameLicense::STATUS_REVOKED,
            'game_service_id' => null,
            'compute_instance_id' => null,
            'released_at' => now(),
        ]);
    }

    /**
     * Return a masked representation of a license for display in admin UIs.
     * Never returns the plaintext key.
     */
    public function mask(GameLicense $license): string
    {
        $plain = $this->encrypter->decrypt($license->license_key);
        $len = strlen($plain);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($plain, 0, 4) . str_repeat('*', $len - 8) . substr($plain, -4);
    }
}
