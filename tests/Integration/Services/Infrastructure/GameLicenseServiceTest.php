<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\GameLicense;
use Pterodactyl\Models\GameLicensePool;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Infrastructure\GameLicenseService;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class GameLicenseServiceTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    protected GameLicenseService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(GameLicenseService::class);
    }

    private function makePool(): GameLicensePool
    {
        return GameLicensePool::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Test License Pool',
            'slug' => 'test-license-pool-' . Str::random(6),
            'provider' => 'giants',
            'license_variable' => 'GAME_LICENSE',
            'enabled' => true,
        ]);
    }

    public function test_license_keys_are_stored_encrypted(): void
    {
        $pool = $this->makePool();

        $license = $this->service->addLicense($pool, 'TEST-LICENSE-12345');

        // The raw column value must not equal the plaintext key.
        $raw = GameLicense::query()->where('id', $license->id)->value('license_key');
        $this->assertNotSame('TEST-LICENSE-12345', $raw);
        $this->assertStringNotContainsString('TEST-LICENSE-12345', $raw);
    }

    public function test_license_key_is_hidden_from_serialization(): void
    {
        $pool = $this->makePool();
        $license = $this->service->addLicense($pool, 'TEST-LICENSE-12345');

        $array = $license->toArray();

        $this->assertArrayNotHasKey('license_key', $array);
    }

    public function test_allocate_returns_plaintext_and_marks_allocated(): void
    {
        $pool = $this->makePool();
        $this->service->addLicense($pool, 'TEST-LICENSE-12345');

        $key = $this->service->allocate($pool);

        $this->assertSame('TEST-LICENSE-12345', $key);

        $license = GameLicense::query()->where('game_license_pool_id', $pool->id)->first();
        $this->assertSame(GameLicense::STATUS_ALLOCATED, $license->status);
    }

    public function test_allocate_throws_when_pool_is_empty(): void
    {
        $pool = $this->makePool();

        $this->expectException(InfrastructureException::class);
        $this->service->allocate($pool);
    }

    public function test_release_for_service_returns_license_to_pool(): void
    {
        $pool = $this->makePool();
        $this->service->addLicense($pool, 'TEST-LICENSE-12345');

        $user = User::factory()->create();
        $service = GameService::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'name' => 'Test Service',
            'status' => GameService::STATUS_ACTIVE,
        ]);

        $this->service->allocate($pool, $service);
        $this->service->releaseForService($service);

        $license = GameLicense::query()->first();
        $this->assertSame(GameLicense::STATUS_AVAILABLE, $license->status);
        $this->assertNull($license->game_service_id);
    }

    public function test_mask_never_returns_plaintext(): void
    {
        $pool = $this->makePool();
        $license = $this->service->addLicense($pool, 'SUPER-SECRET-LICENSE-KEY');

        $masked = $this->service->mask($license);

        $this->assertStringNotContainsString('SUPER-SECRET-LICENSE-KEY', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_bulk_add_licenses(): void
    {
        $pool = $this->makePool();

        $added = $this->service->addLicenses($pool, ['KEY-ONE', 'KEY-TWO', 'KEY-THREE']);

        $this->assertSame(3, $added);
        $this->assertSame(3, GameLicense::query()->where('game_license_pool_id', $pool->id)->count());
    }
}
