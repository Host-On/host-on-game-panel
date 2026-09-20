<?php

namespace Pterodactyl\Tests\Integration\Services\Infrastructure;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\BootstrapToken;
use Pterodactyl\Models\ProvisioningJob;
use Pterodactyl\Models\InfrastructureCluster;
use Pterodactyl\Models\InfrastructureHost;
use Pterodactyl\Models\ResourceProfile;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Infrastructure\BootstrapTokenService;
use Pterodactyl\Services\Infrastructure\InfrastructureProviderManager;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;
use Pterodactyl\Services\Infrastructure\Fake\FakeInfrastructureProvider;

/**
 * Verifies the real-mode bootstrap flow: the job pauses in
 * "waiting_for_bootstrap" until the VM calls back through the one-time
 * token, then resumes and completes.
 *
 * The provider is simulated (there is no live Proxmox in the test environment)
 * but the cluster is a REAL "proxmox" type, so the real-mode code paths run.
 */
class RealModeBootstrapTest extends IntegrationTestCase
{
    protected ProvisioningService $provisioning;

    public string $issuedPlainToken = '';

    public function setUp(): void
    {
        parent::setUp();

        // Capture the plaintext of issued bootstrap tokens for the callback.
        $encrypter = $this->app->make(Encrypter::class);
        $test = $this;

        $this->app->instance(BootstrapTokenService::class, new class ($test) extends BootstrapTokenService {
            public function __construct(private $test)
            {
            }

            public function issue($job, $instance = null, int $ttlMinutes = 60): array
            {
                $result = parent::issue($job, $instance, $ttlMinutes);
                $this->test->issuedPlainToken = $result['token'];

                return $result;
            }
        });

        // Use the simulated provider even for real-type clusters so the
        // real-mode provisioning paths can be exercised without live Proxmox.
        $this->app->instance(InfrastructureProviderManager::class, new class ($encrypter) extends InfrastructureProviderManager {
            public function for(InfrastructureCluster $cluster): \Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface
            {
                return new FakeInfrastructureProvider($cluster->name);
            }
        });

        $this->provisioning = $this->app->make(ProvisioningService::class);
    }

    private function makeRealCluster(): InfrastructureCluster
    {
        // Keep the seeded demo clusters from winning placement resolution.
        InfrastructureCluster::query()->where('type', InfrastructureCluster::TYPE_FAKE)->update(['enabled' => false]);

        return InfrastructureCluster::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Real Mode Cluster ' . Str::random(4),
            'type' => InfrastructureCluster::TYPE_PROXMOX,
            'api_url' => 'https://pve.test:8006',
            'auth_user' => 'test@pve!token',
            'auth_token' => encrypt('token-secret'),
            'tls_verify' => true,
            'enabled' => true,
            'maintenance_mode' => false,
        ]);
    }

    public function test_real_mode_pauses_and_resumes_after_bootstrap_callback(): void
    {
        $cluster = $this->makeRealCluster();
        $location = \Pterodactyl\Models\Location::query()->where('short', 'fra')->firstOrFail();
        $profile = ResourceProfile::query()->where('slug', 'game-cloud-32')->firstOrFail();

        // The cloud profile needs a host in the real cluster to pass placement.
        InfrastructureHost::query()->create([
            'uuid' => Str::uuid()->toString(),
            'cluster_id' => $cluster->id,
            'location_id' => $location->id,
            'name' => 'pve-real-01',
            'external_id' => 'pve-real-01',
            'status' => 'online',
            'cpu_cores' => 64,
            'max_memory' => 262144,
            'max_disk' => 4096,
            'placement_weight' => 500,
            'enabled' => true,
            'maintenance_mode' => false,
        ]);

        $user = User::query()->where('username', 'demo')->firstOrFail();

        $job = $this->provisioning->create([
            'user_id' => $user->id,
            'product' => $profile->slug,
            'location' => 'fra',
            'name' => 'Real Mode Cloud',
        ]);

        $status = $this->provisioning->run($job->fresh());

        // Real mode must park at the bootstrap stage.
        $job = $job->fresh();
        $this->assertSame('waiting_for_bootstrap', $status, 'Job failed: ' . ($job->error ?? 'unknown') . ' | cluster: ' . ($job->cluster?->type ?? 'none') . ' | steps: ' . $job->steps->map(fn ($s) => "{$s->step}:{$s->status}")->implode(','));

        $job = $job->fresh();
        $this->assertSame('waiting_for_bootstrap', $job->status);
        $this->assertNotNull($job->wings_node_id, 'Node must be registered before the VM boots.');

        $token = BootstrapToken::query()->where('provisioning_job_id', $job->id)->firstOrFail();
        $this->assertNull($token->used_at, 'The token must be unused until the VM calls back.');

        // The VM calls back through the panel endpoint with its token.
        $this->postJson('/api/hoston/bootstrap', ['token' => 'wrong-token'])
            ->assertStatus(401);

        $response = $this->postJson('/api/hoston/bootstrap', ['token' => $this->issuedPlainToken]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['node_uuid', 'wings' => ['token_id', 'token', 'config']]);

        $token = $token->fresh();
        $this->assertNotNull($token->used_at, 'The token must be consumed by the callback.');

        // The resume dispatch runs synchronously (sync queue in tests).
        $job = $job->fresh();
        $this->assertSame(ProvisioningJob::STATUS_COMPLETED, $job->status);
        $this->assertNotNull($job->compute_instance_id);
        $this->assertNull($job->server_id, 'A cloud has no game server.');

        foreach ($job->steps as $step) {
            $this->assertContains($step->status, ['success', 'skipped'], "Step {$step->step} did not reach a terminal state.");
        }
    }
}
