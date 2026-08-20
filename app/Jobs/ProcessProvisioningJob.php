<?php

namespace Pterodactyl\Jobs;

use Illuminate\Bus\Queueable;
use Pterodactyl\Models\ProvisioningJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Pterodactyl\Services\Infrastructure\Provisioning\ProvisioningService;

/**
 * Executes the provisioning state machine for a single provisioning job.
 *
 * The job is idempotent and resumable: it can be safely retried after a
 * failure or a panel/worker restart and will continue from the correct step.
 */
class ProcessProvisioningJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(public int $provisioningJobId)
    {
        $this->onQueue('standard');
    }

    public function handle(ProvisioningService $service): void
    {
        $job = ProvisioningJob::query()->find($this->provisioningJobId);

        if (!$job) {
            return;
        }

        $service->run($job);
    }
}
