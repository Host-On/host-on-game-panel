<?php

namespace Pterodactyl\Listeners\HostOn;

use Pterodactyl\Models\GameService;
use Pterodactyl\Events\Server\Installed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Activates a game service once Wings reports its game server as installed.
 * Real-mode provisioning completes the infrastructure part immediately and
 * leaves the service in "provisioning" until this callback arrives.
 */
class GameServiceInstallCompleted implements ShouldQueue
{
    public function handle(Installed $event): void
    {
        $service = GameService::query()
            ->where('server_id', $event->server->id)
            ->where('status', GameService::STATUS_PROVISIONING)
            ->first();

        if ($service) {
            $service->update(['status' => GameService::STATUS_ACTIVE]);
        }
    }
}
