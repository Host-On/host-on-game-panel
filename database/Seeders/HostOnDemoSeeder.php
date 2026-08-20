<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Illuminate\Database\Seeder;
use Pterodactyl\Models\GameCatalogEntry;
use Pterodactyl\Models\GameLicensePool;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Models\InfrastructureIpPool;
use Pterodactyl\Models\InfrastructureNetwork;
use Pterodactyl\Models\InfrastructureProvider;
use Pterodactyl\Models\InfrastructureTemplate;
use Pterodactyl\Models\ResourceProfile;

/**
 * Seeds believable, clearly synthetic demo infrastructure for the Host-On
 * Games Panel so that the full provisioning flow can be demonstrated without
 * a real Proxmox cluster.
 */
class HostOnDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!config('hoston.demo.enabled', true)) {
            $this->command?->line('Host-On demo seeding is disabled.');

            return;
        }

        $location = Location::query()->firstOrCreate(
            ['short' => 'fra'],
            ['long' => 'Frankfurt, Germany']
        );

        $provider = InfrastructureProvider::query()->firstOrCreate(
            ['name' => 'Host-On FRA Games (Demo)'],
            [
                'uuid' => (string) Str::uuid(),
                'type' => InfrastructureProvider::TYPE_FAKE,
                'api_url' => 'https://pve-demo.internal:8006',
                'auth_user' => 'demo@pve!hoston',
                'location_id' => $location->id,
                'enabled' => true,
                'maintenance_mode' => false,
                'status' => 'healthy',
            ]
        );

        $cluster = InfrastructureCluster::query()->firstOrCreate(
            ['provider_id' => $provider->id, 'name' => 'Host-On FRA Games'],
            [
                'uuid' => (string) Str::uuid(),
                'location_id' => $location->id,
                'enabled' => true,
                'maintenance_mode' => false,
            ]
        );

        $template = InfrastructureTemplate::query()->firstOrCreate(
            ['provider_id' => $provider->id, 'name' => 'Debian 13 Game Node'],
            [
                'uuid' => (string) Str::uuid(),
                'cluster_id' => $cluster->id,
                'template_vmid' => 9001,
                'storage' => 'local-zfs',
                'bridge' => 'vmbr0',
                'cloud_init_enabled' => true,
                'wings_bootstrap_enabled' => true,
                'default_cpu' => 4,
                'default_memory' => 8192,
                'default_disk' => 60,
                'enabled' => true,
            ]
        );

        $hosts = [
            ['name' => 'game-pve01', 'cpu' => 32, 'mem' => 131072, 'disk' => 2048, 'cpu_load' => 72, 'mem_load' => 58],
            ['name' => 'game-pve02', 'cpu' => 64, 'mem' => 262144, 'disk' => 4096, 'cpu_load' => 31, 'mem_load' => 33],
            ['name' => 'game-pve03', 'cpu' => 32, 'mem' => 131072, 'disk' => 2048, 'cpu_load' => 44, 'mem_load' => 41],
        ];

        foreach ($hosts as $data) {
            InfrastructureHost::query()->firstOrCreate(
                ['provider_id' => $provider->id, 'name' => $data['name']],
                [
                    'uuid' => (string) Str::uuid(),
                    'hostname' => $data['name'] . '.host-on.internal',
                    'cluster_id' => $cluster->id,
                    'location_id' => $location->id,
                    'external_id' => $data['name'],
                    'max_memory' => $data['mem'],
                    'max_disk' => $data['disk'],
                    'cpu_cores' => $data['cpu'],
                    'allocated_memory' => (int) round($data['mem'] * ($data['mem_load'] / 100)),
                    'allocated_disk' => (int) round($data['disk'] * 0.4),
                    'cpu_utilization' => $data['cpu_load'],
                    'memory_utilization' => $data['mem_load'],
                    'disk_utilization' => 40,
                    'enabled' => true,
                    'maintenance_mode' => false,
                ]
            );
        }

        // A maintenance-mode host to demonstrate exclusion from placement.
        InfrastructureHost::query()->firstOrCreate(
            ['provider_id' => $provider->id, 'name' => 'game-pve04'],
            [
                'uuid' => (string) Str::uuid(),
                'hostname' => 'game-pve04.host-on.internal',
                'cluster_id' => $cluster->id,
                'location_id' => $location->id,
                'external_id' => 'game-pve04',
                'max_memory' => 131072,
                'max_disk' => 2048,
                'cpu_cores' => 32,
                'enabled' => true,
                'maintenance_mode' => true,
            ]
        );

        InfrastructureNetwork::query()->firstOrCreate(
            ['name' => 'Frankfurt Gaming Network'],
            [
                'uuid' => (string) Str::uuid(),
                'bridge' => 'vmbr0',
                'gateway' => '203.0.113.1',
                'subnet' => '203.0.113.0/24',
                'location_id' => $location->id,
                'enabled' => true,
            ]
        );

        $pool = InfrastructureIpPool::query()->firstOrCreate(
            ['name' => 'Frankfurt Gaming Public'],
            [
                'uuid' => (string) Str::uuid(),
                'network' => '203.0.113.0/24',
                'subnet' => '255.255.255.0',
                'gateway' => '203.0.113.1',
                'bridge' => 'vmbr0',
                'allocation_start' => '203.0.113.10',
                'allocation_end' => '203.0.113.250',
                'location_id' => $location->id,
                'enabled' => true,
            ]
        );

        // Release the addresses in the pool so they can be allocated to VMs.
        app(\Pterodactyl\Services\Infrastructure\IpPoolService::class)->sync($pool);

        $catalog = $this->seedCatalog();

        $minecraft = $catalog['minecraft'];
        $rust = $catalog['rust'];
        $ark = $catalog['ark'];
        $cs2 = $catalog['cs2'];

        $this->seedProfiles($minecraft, $rust, $ark, $cs2, $template, $location);

        $this->seedFarmingSimulator25($catalog['farming-simulator-25'], $template, $location);

        $this->seedDemoCustomer();
    }

    /**
     * @return array<string, GameCatalogEntry>
     */
    protected function seedCatalog(): array
    {
        $entries = [
            'minecraft' => ['Minecraft', 'Paper', 'Minecraft'],
            'rust' => ['Rust', 'Rust', 'Rust'],
            'ark' => ['ARK: Survival Ascended', 'ARK: Survival Evolved', 'Source Engine'],
            'cs2' => ['Counter-Strike 2', 'Counter-Strike: Global Offensive', 'Source Engine'],
            'palworld' => ['Palworld', null, null],
            'valheim' => ['Valheim', null, null],
            'terraria' => ['Terraria', null, null],
        ];

        $catalog = [];

        foreach ($entries as $slug => [$name, $eggName, $nestName]) {
            $egg = null;
            if ($eggName && $nestName) {
                $nest = Nest::query()->where('name', $nestName)->first();
                $egg = Egg::query()->where('name', $eggName)->when($nest, fn ($q) => $q->where('nest_id', $nest->id))->first();
            }

            $catalog[$slug] = GameCatalogEntry::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'description' => $name . ' game server.',
                    'nest_id' => $egg?->nest_id,
                    'egg_id' => $egg?->id,
                    'default_image' => $egg ? (string) array_values($egg->docker_images ?? [])[0] : null,
                    'min_ram' => 1024,
                    'recommended_ram' => 4096,
                    'ports' => [$this->defaultPort($slug) . '/tcp'],
                    'environment' => [],
                    'enabled' => true,
                    'sort_order' => array_search($slug, array_keys($entries), true),
                ]
            );
        }

        // Farming Simulator 25 — Windows-only title running via Wine/Proton on a
        // Linux container. Licensed by GIANTS; licenses are supplied legitimately
        // into an encrypted pool (no bypass is implemented).
        $catalog['farming-simulator-25'] = GameCatalogEntry::query()->firstOrCreate(
            ['slug' => 'farming-simulator-25'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Farming Simulator 25',
                'description' => 'Farming Simulator 25 dedicated server (Windows-only title running via Wine on Linux).',
                'nest_id' => null,
                'egg_id' => null,
                'default_image' => 'ghcr.io/parkervcp/yolks:wine_latest',
                'runtime' => 'wine',
                'requires_license' => true,
                'license_variable' => 'GAME_LICENSE',
                'min_ram' => 4096,
                'recommended_ram' => 8192,
                'ports' => ['10823/udp', '10823/tcp'],
                'environment' => [],
                'enabled' => true,
                'sort_order' => 10,
            ]
        );

        return $catalog;
    }

    protected function seedProfiles(GameCatalogEntry $minecraft, GameCatalogEntry $rust, GameCatalogEntry $ark, GameCatalogEntry $cs2, InfrastructureTemplate $template, Location $location): void
    {
        $profiles = [
            [
                'slug' => 'minecraft-starter',
                'name' => 'Minecraft Starter',
                'catalog' => $minecraft,
                'cpu' => 4, 'memory' => 8192, 'disk' => 80,
                'game_cpu' => 4, 'game_memory' => 7168, 'game_disk' => 70000,
            ],
            [
                'slug' => 'minecraft-performance',
                'name' => 'Minecraft Performance',
                'catalog' => $minecraft,
                'cpu' => 6, 'memory' => 16384, 'disk' => 100,
                'game_cpu' => 6, 'game_memory' => 14336, 'game_disk' => 90000,
            ],
            [
                'slug' => 'rust-performance',
                'name' => 'Rust Performance',
                'catalog' => $rust,
                'cpu' => 8, 'memory' => 24576, 'disk' => 150,
                'game_cpu' => 8, 'game_memory' => 21504, 'game_disk' => 140000,
            ],
            [
                'slug' => 'ark-performance',
                'name' => 'ARK Performance',
                'catalog' => $ark,
                'cpu' => 8, 'memory' => 32768, 'disk' => 200,
                'game_cpu' => 8, 'game_memory' => 28672, 'game_disk' => 190000,
            ],
            [
                'slug' => 'game-cloud-32',
                'name' => 'Game Cloud 32',
                'catalog' => $cs2,
                'cpu' => 8, 'memory' => 32768, 'disk' => 250,
                'game_cpu' => 8, 'game_memory' => 30720, 'game_disk' => 240000,
            ],
        ];

        foreach ($profiles as $profile) {
            ResourceProfile::query()->firstOrCreate(
                ['slug' => $profile['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $profile['name'],
                    'description' => $profile['name'] . ' game hosting plan.',
                    'game_catalog_id' => $profile['catalog']->id,
                    'nest_id' => $profile['catalog']->nest_id,
                    'egg_id' => $profile['catalog']->egg_id,
                    'cpu' => $profile['cpu'],
                    'memory' => $profile['memory'],
                    'disk' => $profile['disk'],
                    'game_cpu' => $profile['game_cpu'],
                    'game_memory' => $profile['game_memory'],
                    'game_disk' => $profile['game_disk'],
                    'system_reserve' => $profile['memory'] - $profile['game_memory'],
                    'ports' => $profile['catalog']->ports,
                    'backups' => 3,
                    'infrastructure_type' => ResourceProfile::INFRA_DEDICATED_VM,
                    'template_id' => $template->id,
                    'location_id' => $location->id,
                    'enabled' => true,
                ]
            );
        }
    }

    /**
     * Seed the Farming Simulator 25 title: a Wine-based resource profile and
     * an empty (legitimate) GIANTS license pool.
     */
    protected function seedFarmingSimulator25(GameCatalogEntry $catalog, InfrastructureTemplate $template, Location $location): void
    {
        ResourceProfile::query()->firstOrCreate(
            ['slug' => 'farming-simulator-25'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Farming Simulator 25',
                'description' => 'Farming Simulator 25 dedicated server via Wine.',
                'game_catalog_id' => $catalog->id,
                'nest_id' => $catalog->nest_id,
                'egg_id' => $catalog->egg_id,
                'cpu' => 6,
                'memory' => 16384,
                'disk' => 100,
                'game_cpu' => 6,
                'game_memory' => 14336,
                'game_disk' => 90000,
                'system_reserve' => 2048,
                'ports' => $catalog->ports,
                'backups' => 3,
                'infrastructure_type' => ResourceProfile::INFRA_DEDICATED_VM,
                'template_id' => $template->id,
                'location_id' => $location->id,
                'enabled' => true,
            ]
        );

        // The license pool starts empty — GIANTS licenses must be added by the
        // administrator before this title can be provisioned.
        GameLicensePool::query()->firstOrCreate(
            ['slug' => 'farming-simulator-25'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Farming Simulator 25',
                'game_catalog_id' => $catalog->id,
                'provider' => 'giants',
                'license_type' => 'dedicated',
                'license_variable' => 'GAME_LICENSE',
                'enabled' => true,
            ]
        );
    }

    protected function seedDemoCustomer(): void
    {
        if (!User::query()->where('username', 'demo')->exists()) {
            User::query()->create([
                'uuid' => (string) Str::uuid(),
                'external_id' => null,
                'username' => 'demo',
                'email' => 'demo@host-on.games',
                'name_first' => 'Demo',
                'name_last' => 'Customer',
                'password' => bcrypt('hoston-demo'),
                'language' => 'en',
                'root_admin' => false,
            ]);
        }

        if (!User::query()->where('username', 'admin')->exists()) {
            User::query()->create([
                'uuid' => (string) Str::uuid(),
                'external_id' => null,
                'username' => 'admin',
                'email' => 'admin@host-on.games',
                'name_first' => 'Admin',
                'name_last' => 'Host-On',
                'password' => bcrypt('hoston-admin'),
                'language' => 'en',
                'root_admin' => true,
            ]);
        }
    }

    protected function defaultPort(string $slug): string
    {
        return match ($slug) {
            'rust' => '28015',
            'ark' => '7777',
            'cs2' => '27015',
            'terraria' => '7777',
            default => '25565',
        };
    }
}
