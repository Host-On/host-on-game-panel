<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\User;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\GameLicense;
use Pterodactyl\Models\GameService;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Models\GameCatalogEntry;
use Pterodactyl\Models\GameLicensePool;
use Pterodactyl\Models\ResourceProfile;
use Pterodactyl\Models\InfrastructureTemplate;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Infrastructure\GameLicenseService;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

class GameLicenseProvisioningTest extends IntegrationTestCase
{
    protected ProvisioningService $provisioning;

    protected GameLicenseService $licenses;

    public function setUp(): void
    {
        parent::setUp();
        $this->provisioning = $this->app->make(ProvisioningService::class);
        $this->licenses = $this->app->make(GameLicenseService::class);
    }

    public function test_license_is_injected_into_server_and_not_exposed(): void
    {
        // Reuse the seeded Paper egg and add a hidden GAME_LICENSE variable.
        $egg = Egg::query()->where('name', 'Paper')->firstOrFail();

        EggVariable::query()->create([
            'egg_id' => $egg->id,
            'name' => 'Game License',
            'env_variable' => 'GAME_LICENSE',
            'default_value' => '',
            'user_viewable' => false,
            'user_editable' => false,
            'rules' => 'nullable|string',
        ]);

        $catalog = GameCatalogEntry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'FS25 Test',
            'slug' => 'fs25-test-' . Str::random(6),
            'nest_id' => $egg->nest_id,
            'egg_id' => $egg->id,
            'default_image' => 'ghcr.io/parkervcp/yolks:wine_latest',
            'runtime' => 'wine',
            'requires_license' => true,
            'license_variable' => 'GAME_LICENSE',
            'min_ram' => 1024,
            'recommended_ram' => 4096,
            'ports' => ['10823/udp'],
            'environment' => [],
            'enabled' => true,
            'sort_order' => 0,
        ]);

        $template = InfrastructureTemplate::query()->first();
        $profile = ResourceProfile::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'FS25 Test Profile',
            'slug' => 'fs25-test-profile-' . Str::random(6),
            'game_catalog_id' => $catalog->id,
            'nest_id' => $egg->nest_id,
            'egg_id' => $egg->id,
            'cpu' => 2,
            'memory' => 4096,
            'disk' => 40,
            'game_cpu' => 2,
            'game_memory' => 2048,
            'game_disk' => 20000,
            'system_reserve' => 2048,
            'ports' => ['10823/udp'],
            'backups' => 0,
            'infrastructure_type' => ResourceProfile::INFRA_DEDICATED_VM,
            'template_id' => $template?->id,
            'enabled' => true,
        ]);

        $pool = GameLicensePool::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'FS25 Test Pool',
            'slug' => 'fs25-test-pool-' . Str::random(6),
            'game_catalog_id' => $catalog->id,
            'provider' => 'giants',
            'license_variable' => 'GAME_LICENSE',
            'enabled' => true,
        ]);

        $this->licenses->addLicense($pool, 'SUPER-SECRET-FS25-LICENSE');

        $user = User::query()->where('username', 'demo')->firstOrFail();

        $job = $this->provisioning->create([
            'user_id' => $user->id,
            'product' => $profile->slug,
            'location' => 'fra',
            'name' => 'FS25 Server',
        ]);

        $status = $this->provisioning->run($job->fresh());

        $this->assertSame('completed', $status);

        // The license must have been allocated to the service.
        $license = GameLicense::query()->first();
        $this->assertSame(GameLicense::STATUS_ALLOCATED, $license->status);
        $this->assertNotNull($license->game_service_id);

        // The license plaintext must be present in the server variables (which
        // Wings consumes) but not in any serialized license representation.
        $server = $job->fresh()->server;
        $variable = ServerVariable::query()->where('server_id', $server->id)->whereHas('variable', fn ($q) => $q->where('env_variable', 'GAME_LICENSE'))->first();

        $this->assertNotNull($variable, 'GAME_LICENSE server variable should exist.');
        $this->assertSame('SUPER-SECRET-FS25-LICENSE', $variable->variable_value);

        $this->assertArrayNotHasKey('license_key', $license->toArray());
    }
}
