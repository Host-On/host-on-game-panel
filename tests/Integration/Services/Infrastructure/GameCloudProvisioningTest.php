<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Pterodactyl\Models\User;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

/**
 * Verifies the "Game Cloud" model: the customer orders a cloud (VM + Wings
 * node, no game server) and then installs multiple game servers onto it.
 */
class GameCloudProvisioningTest extends IntegrationTestCase
{
    protected ProvisioningService $provisioning;

    public function setUp(): void
    {
        parent::setUp();
        $this->provisioning = $this->app->make(ProvisioningService::class);
    }

    public function test_game_cloud_then_shared_games_flow(): void
    {
        $user = User::query()->where('username', 'demo')->firstOrFail();

        // 1. Order the Game Cloud: creates a VM + managed Wings node but NO
        //    game server.
        $cloudJob = $this->provisioning->create([
            'user_id' => $user->id,
            'product' => 'game-cloud-32',
            'location' => 'fra',
            'name' => 'My Game Cloud',
        ]);

        $status = $this->provisioning->run($cloudJob->fresh());

        $this->assertSame('completed', $status);

        $cloudJob = $cloudJob->fresh();
        $cloud = $cloudJob->service;
        $instance = $cloudJob->computeInstance;

        $this->assertNotNull($instance, 'The cloud must have created a compute instance.');
        $this->assertNotNull($instance->wings_node_id, 'The cloud must have a registered Wings node.');
        $this->assertNotNull($instance->game_ip, 'The cloud must have a public IP.');
        $this->assertNull($cloudJob->server_id, 'The cloud itself must not have a game server.');
        $this->assertSame(GameService::STATUS_ACTIVE, $cloud->status);

        // 2. Install a Minecraft server onto the cloud: no new VM, no new node,
        //    only a game server + allocation on the existing cloud.
        $gameJob = $this->provisioning->create([
            'user_id' => $user->id,
            'product' => 'minecraft-on-cloud',
            'location' => 'fra',
            'name' => 'Minecraft on Cloud',
        ]);

        $status = $this->provisioning->run($gameJob->fresh());

        $this->assertSame('completed', $status);

        $gameJob = $gameJob->fresh();

        $this->assertSame($instance->id, $gameJob->compute_instance_id, 'The shared game must reuse the existing cloud VM.');
        $this->assertSame($instance->wings_node_id, $gameJob->wings_node_id, 'The shared game must reuse the existing Wings node.');
        $this->assertNotNull($gameJob->server_id, 'The shared game must have created a game server.');
        $this->assertSame($instance->game_ip, $gameJob->server->allocation->ip, 'The shared game uses the cloud public IP.');

        // 3. Install a second game onto the same cloud: it must get its own
        //    port on the same VM.
        $rustJob = $this->provisioning->create([
            'user_id' => $user->id,
            'product' => 'rust-on-cloud',
            'location' => 'fra',
            'name' => 'Rust on Cloud',
        ]);

        $this->provisioning->run($rustJob->fresh());

        $rustJob = $rustJob->fresh();

        $this->assertSame($instance->id, $rustJob->compute_instance_id);
        $this->assertSame($instance->wings_node_id, $rustJob->wings_node_id);
        $this->assertNotSame($gameJob->server->allocation->port, $rustJob->server->allocation->port, 'Each shared game must have a distinct port.');

        // The cloud VM still hosts exactly one node and multiple servers.
        $this->assertSame(2, GameService::query()->where('compute_instance_id', $instance->id)->whereNotNull('server_id')->count());
    }

    public function test_shared_game_without_cloud_fails_cleanly(): void
    {
        // A fresh user without a cloud cannot order a shared game.
        $user = User::factory()->create();

        $job = $this->provisioning->create([
            'user_id' => $user->id,
            'product' => 'minecraft-on-cloud',
            'location' => 'fra',
            'name' => 'Minecraft without cloud',
        ]);

        $status = $this->provisioning->run($job->fresh());

        $this->assertSame('failed', $status);
        $this->assertStringContainsString('no game cloud', strtolower($job->fresh()->error));
    }
}
