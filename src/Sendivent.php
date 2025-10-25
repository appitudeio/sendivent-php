<?php

namespace Sendivent;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;

class Sendivent
{
    private Client $client;
    private string|null $event = null;
    private string|array|null $to = null;
    private array $payload = [];
    private string|null $channel = null;
    private string|null $language = null;
    private array $overrides = [];
    private string|null $idempotencyKey = null;

    public function __construct(string $apiKey)
    {
        if (!preg_match('/^(test_|live_)/', $apiKey)) {
            throw new \InvalidArgumentException(
                "API key must start with 'test_' or 'live_'"
            );
        }

        $baseUri = str_starts_with($apiKey, 'live_')
            ? 'https://api.sendivent.com/'
            : 'https://api-sandbox.sendivent.com/';

        $this->client = new Client([
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Sendivent-PHP/1.0'
            ],
            'timeout' => 30,
            'http_errors' => true
        ]);
    }

    /**
     * Set the event name
     */
    public function event(string $event): self
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Set recipient(s)
     *
     * @param string|array{id?: string, name?: string, avatar?: string, email?: string, phone?: string, slack_id?: string, meta?: array<string, mixed>}|list<string|array{id?: string, name?: string, avatar?: string, email?: string, phone?: string, slack_id?: string, meta?: array<string, mixed>}> $recipient
     *   String: Direct identifier (email, phone, slack ID, etc.)
     *   Array: Contact with channel identifiers (email, phone, slack_id) and optional metadata
     *   List of above: Multiple recipients
     */
    public function to(string|array $recipient): self
    {
        $this->to = $recipient;
        return $this;
    }

    public function payload(array $data): self
    {
        $this->payload = $data;
        return $this;
    }

    public function channel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }

    public function language(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function overrides(array $overrides): self
    {
        $this->overrides = array_merge($this->overrides, $overrides);
        return $this;
    }

    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    /**
     * Send the notification (blocking - waits for response)
     */
    public function send(): SendResponse
    {
        if ($this->event === null) {
            throw new \InvalidArgumentException('Event name must be set using event() method');
        }

        [$endpoint, $options] = $this->buildRequestOptions();

        try {
            $response = $this->client->request('POST', $endpoint, $options);
            return SendResponse::from(json_decode($response->getBody(), true));
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                'Sendivent API request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Send the notification asynchronously (fire-and-forget)
     *
     * @return PromiseInterface
     */
    public function sendAsync(): PromiseInterface
    {
        if ($this->event === null) {
            throw new \InvalidArgumentException('Event name must be set using event() method');
        }

        [$endpoint, $options] = $this->buildRequestOptions();
        return $this->client->requestAsync('POST', $endpoint, $options);
    }

    private function buildRequestOptions(): array
    {
        $endpoint = 'send/' . $this->event;
        if ($this->channel) {
            $endpoint .= '/' . $this->channel;
        }

        $body = ['payload' => $this->payload];

        if ($this->to !== null) {
            $body['to'] = $this->to;
        }

        if ($this->language !== null) {
            $body['language'] = $this->language;
        }

        if (!empty($this->overrides)) {
            $body['overrides'] = $this->overrides;
        }

        $headers = [];
        if ($this->idempotencyKey !== null) {
            $headers['X-Idempotency-Key'] = $this->idempotencyKey;
        }

        return [
            $endpoint,
            [
                'json' => $body,
                'headers' => $headers
            ]
        ];
    }
}
