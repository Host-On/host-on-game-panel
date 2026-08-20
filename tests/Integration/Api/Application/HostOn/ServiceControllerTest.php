<?php

namespace Pterodactyl\Tests\Integration\Api\Application\HostOn;

use Pterodactyl\Models\User;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;

class ServiceControllerTest extends ApplicationApiIntegrationTestCase
{
    public function test_create_service_endpoint_returns_provisioning_ids(): void
    {
        $profile = ResourceProfile::query()->where('slug', 'minecraft-starter')->firstOrFail();
        $user = User::factory()->create();

        $response = $this->postJson('/api/application/hoston/services', [
            'user_id' => $user->id,
            'product' => $profile->slug,
            'location' => 'fra',
            'name' => 'Test Minecraft',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'service_id',
            'service_external_id',
            'provisioning_id',
            'provisioning_external_id',
            'status',
        ]);
    }

    public function test_show_service_endpoint(): void
    {
        $profile = ResourceProfile::query()->where('slug', 'minecraft-starter')->firstOrFail();
        $user = User::factory()->create();

        $created = $this->postJson('/api/application/hoston/services', [
            'user_id' => $user->id,
            'product' => $profile->slug,
            'location' => 'fra',
        ])->assertStatus(202);

        $service = GameService::query()->where('uuid', $created->json('service_id'))->firstOrFail();

        $this->getJson('/api/application/hoston/services/' . $service->uuid)
            ->assertStatus(200)
            ->assertJsonStructure([
                'service_id',
                'external_id',
                'status',
                'name',
                'provisioning',
            ]);
    }

    public function test_create_service_requires_valid_product(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/application/hoston/services', [
            'user_id' => $user->id,
            'product' => 'nonexistent-product',
        ])->assertStatus(404);
    }
}
