<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\ComputeInstance;
use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

/**
 * Verifies that the data model allows multiple game services on a single
 * compute instance (the future "Game Cloud" product), even though the current
 * provisioning rule is one order → one VM → one game server.
 */
class ComputeInstanceMultiServerTest extends IntegrationTestCase
{
    public function test_multiple_game_services_can_share_one_compute_instance(): void
    {
        $user = User::factory()->create();

        $instance = ComputeInstance::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Game Cloud VM',
            'customer_id' => $user->id,
            'vmid' => '18100',
            'status' => ComputeInstance::STATUS_RUNNING,
            'cpu' => 8,
            'memory' => 32768,
            'disk' => 250,
        ]);

        // Multiple game services may reference the same compute instance.
        $minecraft = GameService::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'name' => 'Minecraft',
            'compute_instance_id' => $instance->id,
            'status' => GameService::STATUS_ACTIVE,
        ]);

        $velocity = GameService::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'name' => 'Velocity',
            'compute_instance_id' => $instance->id,
            'status' => GameService::STATUS_ACTIVE,
        ]);

        $this->assertSame($instance->id, $minecraft->compute_instance_id);
        $this->assertSame($instance->id, $velocity->compute_instance_id);
        $this->assertCount(2, $instance->gameServices);
        $this->assertNull($minecraft->server_id, 'A game service may exist before its Pterodactyl server is created.');
    }
}
