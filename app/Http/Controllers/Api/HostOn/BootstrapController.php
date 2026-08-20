<?php

namespace Pterodactyl\Http\Controllers\Api\HostOn;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\ProvisioningStep;
use Pterodactyl\Models\ProvisioningJob;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Jobs\ProcessProvisioningJob;
use Pterodactyl\Services\Infrastructure\BootstrapTokenService;

/**
 * Called by a freshly booted customer VM to retrieve its Wings bootstrap
 * configuration using a one-time token.
 *
 * The token is validated once, after which the remaining provisioning steps
 * (node registration -> game server creation) are resumed asynchronously.
 */
class BootstrapController extends Controller
{
    public function __construct(protected BootstrapTokenService $tokens)
    {
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $token = $request->input('token') ?: $request->bearerToken();

        if (!$token) {
            return new JsonResponse(['error' => 'A bootstrap token is required.'], 401);
        }

        try {
            $job = $this->tokens->consume((string) $token);
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => 'Invalid or expired bootstrap token.'], 401);
        }

        $node = $job->node;

        if (!$node) {
            return new JsonResponse(['error' => 'No node has been registered for this provisioning job.'], 409);
        }

        ProvisioningStep::query()
            ->where('provisioning_job_id', $job->id)
            ->where('step', ProvisioningJob::STEP_INSTALLING_WINGS)
            ->update(['status' => ProvisioningStep::STATUS_SUCCESS, 'finished_at' => now(), 'message' => 'Wings installed and connected.']);

        // Resume the provisioning job from the next step.
        ProcessProvisioningJob::dispatch($job->id);

        return new JsonResponse([
            'node_uuid' => $node->uuid,
            'wings' => [
                'token_id' => $node->daemon_token_id,
                'token' => $node->getDecryptedKey(),
                'config' => $node->getYamlConfiguration(),
            ],
        ]);
    }
}
