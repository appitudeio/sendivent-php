<?php

namespace Sendivent;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Sendivent\Exception\SendiventException;

class Contacts
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Get a contact by any identifier (email, phone, UUID, external_id, slack)
     *
     * @return array{success: bool, contact: array<string, mixed>}
     */
    public function get(string $identifier): array
    {
        return $this->request('GET', 'v1/contacts/' . urlencode($identifier));
    }

    /**
     * Create or update a contact
     * Resolves by any identifier in the data (id, email, phone, etc.)
     *
     * @param array{id?: string, name?: string, email?: string, phone?: string, slack?: string, push_token?: string, push_tokens?: list<string>, meta?: array<string, mixed>} $data
     * @return array{success: bool, contact: array<string, mixed>}
     */
    public function upsert(array $data): array
    {
        return $this->request('POST', 'v1/contacts', $data);
    }

    /**
     * Update an existing contact (404 if not found)
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, contact: array<string, mixed>}
     */
    public function update(string $identifier, array $data): array
    {
        return $this->request('PATCH', 'v1/contacts/' . urlencode($identifier), $data);
    }

    /**
     * Delete a contact (hard delete for GDPR compliance)
     *
     * @return array{success: bool}
     */
    public function delete(string $identifier): array
    {
        return $this->request('DELETE', 'v1/contacts/' . urlencode($identifier));
    }

    /**
     * Register a push token on a contact (additive — doesn't remove other tokens)
     * Call this from your mobile app on startup to register the device.
     *
     * @return array{success: bool, contact: array<string, mixed>}
     */
    public function registerPushToken(string $identifier, string $token): array
    {
        return $this->request('POST', 'v1/contacts/' . urlencode($identifier) . '/push-tokens', [
            'token' => $token
        ]);
    }

    /**
     * Remove a push token from a contact (e.g., on user logout)
     *
     * @return array{success: bool, contact: array<string, mixed>}
     */
    public function removePushToken(string $identifier, string $token): array
    {
        return $this->request('DELETE', 'v1/contacts/' . urlencode($identifier) . '/push-tokens', [
            'token' => $token
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception\ApiException       The API answered with a non-2xx status
     * @throws Exception\TransportException The request never reached the API
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->client->request($method, $path, $options);
        } catch (GuzzleException $e) {
            throw SendiventException::fromGuzzle($e);
        }

        // Decode defensively — an unparseable 2xx body must not be a TypeError
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
