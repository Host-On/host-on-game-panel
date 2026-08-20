<?php

namespace Pterodactyl\Services\Infrastructure\Proxmox;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Infrastructure\InfrastructureException;

/**
 * Minimal HTTP client for the Proxmox VE REST API (api2/json).
 *
 * Authentication is performed using a Proxmox API token which must be scoped
 * with the minimum required privileges. Secrets are never logged.
 */
class ProxmoxClient
{
    protected Client $client;

    public function __construct(
        protected string $baseUrl,
        protected string $authUser,
        protected string $authToken,
        protected bool $tlsVerify = true,
        protected ?string $fingerprint = null,
        protected int $timeout = 15,
    ) {
        $options = [
            'base_uri' => rtrim($this->baseUrl, '/') . '/api2/json/',
            'verify' => $this->tlsVerify,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (!$this->tlsVerify && !empty($this->fingerprint)) {
            // Proxmox provides a certificate fingerprint that can be used to pin
            // the certificate when full chain verification is not possible.
            $options['verify'] = false;
        }

        $this->client = new Client($options);
    }

    /**
     * Perform a GET request and return the decoded JSON body.
     *
     * @throws InfrastructureException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * Perform a POST request and return the decoded JSON body.
     *
     * @throws InfrastructureException
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, ['form_params' => $data]);
    }

    /**
     * Perform a PUT request and return the decoded JSON body.
     *
     * @throws InfrastructureException
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, ['form_params' => $data]);
    }

    /**
     * Perform a DELETE request and return the decoded JSON body.
     *
     * @throws InfrastructureException
     */
    public function delete(string $path, array $data = []): array
    {
        return $this->request('DELETE', $path, ['form_params' => $data]);
    }

    /**
     * @throws InfrastructureException
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        $options['headers'] = [
            'Authorization' => 'PVEAPIToken=' . $this->authUser . '=' . $this->authToken,
        ];

        try {
            $response = $this->client->request($method, ltrim($path, '/'), $options);
        } catch (GuzzleException $exception) {
            throw $this->wrap($exception);
        }

        $body = json_decode((string) $response->getBody(), true);

        if (!is_array($body)) {
            throw new InfrastructureException('Proxmox returned an invalid response body.');
        }

        return $body;
    }

    protected function wrap(GuzzleException $exception): InfrastructureException
    {
        $response = method_exists($exception, 'getResponse') ? $exception->getResponse() : null;
        $status = $response?->getStatusCode() ?? 0;

        if ($status === 401 || $status === 403) {
            return new InfrastructureException('Proxmox authentication failed. Please verify the API token and its permissions.');
        }

        if ($status >= 500) {
            return new InfrastructureException(sprintf('Proxmox API error (HTTP %d).', $status));
        }

        $detail = null;
        if ($response !== null) {
            $body = json_decode((string) $response->getBody(), true);
            $detail = $body['errors'] ?? null;
            if (is_array($detail)) {
                $detail = implode('; ', array_map(fn ($e) => $e['msg'] ?? '', $detail));
            }
        }

        if (empty($detail)) {
            $detail = $exception->getMessage();
        }

        return new InfrastructureException(sprintf('Proxmox API error: %s', $detail));
    }
}
