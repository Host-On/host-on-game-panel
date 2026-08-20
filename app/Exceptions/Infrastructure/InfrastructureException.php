<?php

namespace Pterodactyl\Exceptions\Infrastructure;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Thrown when a call to an infrastructure provider (e.g. Proxmox VE) fails.
 *
 * The message should be safe to surface to administrators and never include
 * credentials, tokens or other secrets.
 */
class InfrastructureException extends DisplayException
{
}
