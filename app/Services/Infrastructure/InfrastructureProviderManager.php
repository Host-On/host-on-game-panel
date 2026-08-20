<?php

namespace Pterodactyl\Services\Infrastructure;

use Pterodactyl\Models\InfrastructureProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;
use Pterodactyl\Services\Infrastructure\Fake\FakeInfrastructureProvider;
use Pterodactyl\Services\Infrastructure\Proxmox\ProxmoxClient;
use Pterodactyl\Services\Infrastructure\Proxmox\ProxmoxInfrastructureProvider;
use Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface;

/**
 * Resolves the correct InfrastructureProviderInterface implementation for a
 * given provider model. This is the single seam through which the rest of the
 * application obtains an infrastructure provider.
 */
class InfrastructureProviderManager
{
    public function __construct(protected Encrypter $encrypter)
    {
    }

    /**
     * Build the provider implementation for the given provider model.
     *
     * @throws InfrastructureException
     */
    public function for(InfrastructureProvider $provider): InfrastructureProviderInterface
    {
        return match ($provider->type) {
            InfrastructureProvider::TYPE_FAKE => new FakeInfrastructureProvider(),
            InfrastructureProvider::TYPE_PROXMOX => $this->buildProxmox($provider),
            default => throw new InfrastructureException(sprintf('Unsupported infrastructure provider type "%s".', $provider->type)),
        };
    }

    protected function buildProxmox(InfrastructureProvider $provider): ProxmoxInfrastructureProvider
    {
        if (empty($provider->api_url) || empty($provider->auth_user)) {
            throw new InfrastructureException('This Proxmox provider is missing connection details (API URL or token user).');
        }

        $token = null;
        if (!empty($provider->auth_token)) {
            $token = $this->encrypter->decrypt($provider->auth_token);
        }

        if (empty($token)) {
            throw new InfrastructureException('This Proxmox provider has no API token configured.');
        }

        $client = new ProxmoxClient(
            baseUrl: $provider->api_url,
            authUser: $provider->auth_user,
            authToken: $token,
            tlsVerify: $provider->tls_verify,
            fingerprint: $provider->tls_fingerprint,
            timeout: $provider->timeout ?? 15,
        );

        return new ProxmoxInfrastructureProvider($client);
    }
}
