<?php

namespace Pterodactyl\Services\Infrastructure;

use Pterodactyl\Models\InfrastructureCluster;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;
use Pterodactyl\Services\Infrastructure\Fake\FakeInfrastructureProvider;
use Pterodactyl\Services\Infrastructure\Proxmox\ProxmoxClient;
use Pterodactyl\Services\Infrastructure\Proxmox\ProxmoxInfrastructureProvider;
use Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface;

/**
 * Resolves the correct InfrastructureProviderInterface implementation for a
 * given cluster. This is the internal technical abstraction: clusters carry
 * their own connection configuration, and this manager turns a cluster into
 * the concrete provider implementation (Proxmox, fake/demo, ...).
 */
class InfrastructureProviderManager
{
    public function __construct(protected Encrypter $encrypter)
    {
    }

    /**
     * Build the provider implementation for the given cluster.
     *
     * @throws InfrastructureException
     */
    public function for(InfrastructureCluster $cluster): InfrastructureProviderInterface
    {
        return match ($cluster->type) {
            InfrastructureCluster::TYPE_FAKE => new FakeInfrastructureProvider($cluster->name),
            InfrastructureCluster::TYPE_PROXMOX => $this->buildProxmox($cluster),
            default => throw new InfrastructureException(sprintf('Unsupported infrastructure provider type "%s".', $cluster->type)),
        };
    }

    protected function buildProxmox(InfrastructureCluster $cluster): ProxmoxInfrastructureProvider
    {
        if (empty($cluster->api_url) || empty($cluster->auth_user)) {
            throw new InfrastructureException('This Proxmox cluster is missing connection details (API URL or token user).');
        }

        $token = null;
        if (!empty($cluster->auth_token)) {
            $token = $this->encrypter->decrypt($cluster->auth_token);
        }

        if (empty($token)) {
            throw new InfrastructureException('This Proxmox cluster has no API token configured.');
        }

        $client = new ProxmoxClient(
            baseUrl: $cluster->api_url,
            authUser: $cluster->auth_user,
            authToken: $token,
            tlsVerify: $cluster->tls_verify,
            fingerprint: $cluster->tls_fingerprint,
            timeout: $cluster->timeout ?? 15,
        );

        return new ProxmoxInfrastructureProvider($client);
    }
}
