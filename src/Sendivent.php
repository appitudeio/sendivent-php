<?php

namespace Sendivent;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

class Sendivent
{
    private const API_URLS = [
        'sandbox' => 'https://api-sandbox.sendivent.com/',
        'production' => 'https://api.sendivent.com/'
    ];

    private Client $client;
    private string $baseUri;
    private string $apiKey;
    private string|null $event = null;
    private string|array|null $to = null;
    private string|array|null $from = null;
    private array $payload = [];
    private string|null $channel = null;
    private string|null $language = null;
    private array $overrides = [];
    private string|null $idempotencyKey = null;

    public function __construct(string $apiKey)
    {
        if (!preg_match('/^(test_|live_)/', $apiKey)) {
            throw new InvalidArgumentException(
                "API key must start with 'test_' or 'live_'"
            );
        }

        $this->apiKey = $apiKey;
        $this->baseUri = str_starts_with($apiKey, 'live_')
            ? self::API_URLS['production']
            : self::API_URLS['sandbox'];

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Sendivent-PHP/0.1.0'
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

    /**
     * Set sender/from contact (for impersonation or reply-to)
     *
     * @param string|array{id?: string, name?: string, avatar?: string, email?: string, phone?: string, slack_id?: string, meta?: array<string, mixed>} $sender
     *   String: Direct identifier (email, phone, slack ID, etc.)
     *   Array: Contact with channel identifiers and optional metadata
     */
    public function from(string|array $sender): self
    {
        $this->from = $sender;
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
            throw new InvalidArgumentException('Event name must be set using event() method');
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
     * Uses fsockopen for true non-blocking fire-and-forget behavior.
     * Request is sent immediately and function returns without waiting for response.
     */
    public function sendAsync(): void
    {
        if ($this->event === null) {
            throw new InvalidArgumentException('Event name must be set using event() method');
        }

        [$endpoint, $options] = $this->buildRequestOptions();

        // Build full URL
        $url = rtrim($this->baseUri, '/') . '/' . $endpoint;

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Sendivent-PHP/0.1.0'
        ];

        // Add custom headers (like idempotency key)
        if (!empty($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        // Send via fire-and-forget
        $this->fireAndForget($url, $options['json'], $headers);
    }

    private function buildRequestOptions(): array
    {
        $endpoint = 'v1/send/' . $this->event;
        if ($this->channel) {
            $endpoint .= '/' . $this->channel;
        }

        $body = ['payload' => $this->payload];

        if ($this->to !== null) {
            $body['to'] = $this->to;
        }

        if ($this->from !== null) {
            $body['from'] = $this->from;
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

    /**
     * Fire-and-forget HTTP request using fsockopen
     *
     * Sends the HTTP request immediately and returns without waiting for response.
     * Uses native PHP sockets with blocking mode to ensure data is actually sent.
     *
     * @param string $url Full URL to send request to
     * @param array $data Request body data (will be JSON encoded)
     * @param array $headers HTTP headers
     */
    private function fireAndForget(string $url, array $data, array $headers): void
    {
        $parts = parse_url($url);
        if (!$parts || !isset($parts['host'])) {
            return; // Silent failure for fire-and-forget
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $path = ($parts['path'] ?? '/');
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        // Use tls:// for HTTPS
        $prefix = ($parts['scheme'] === 'https') ? 'tls://' : '';

        // Open socket connection
        $fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 5);
        if (!$fp) {
            return; // Silent failure for fire-and-forget
        }

        // Keep in blocking mode to ensure write completes before close
        // (non-blocking mode causes fwrite to queue data that gets discarded on immediate fclose)

        // Build HTTP request
        $body = json_encode($data);
        $request = "POST $path HTTP/1.1\r\n";
        $request .= "Host: $host\r\n";

        // Add all headers
        foreach ($headers as $key => $value) {
            $request .= "$key: $value\r\n";
        }

        $request .= "Content-Length: " . strlen($body) . "\r\n";
        $request .= "Connection: Close\r\n\r\n";
        $request .= $body;

        // Send request and close immediately
        fwrite($fp, $request);
        fclose($fp);
    }
}