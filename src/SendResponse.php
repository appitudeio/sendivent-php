<?php

namespace Sendivent;

/**
 * Response from Sendivent send API
 *
 * Unified response format: { id, event, status }
 * The id is a notification tracking UUID.
 * Query status via GET /v1/notifications/{id}
 */
class SendResponse
{
    public function __construct(
        /** Notification ID (sequence run UUID) */
        public readonly string $id,
        /** Event identifier that was triggered */
        public readonly string $event,
        /** Status: "accepted" means the notification is being processed */
        public readonly string $status,
        public readonly ?string $error = null,
    ) {}

    /**
     * Build a response object from a decoded body.
     *
     * Tolerates null and partial bodies on purpose: by the time we get here the
     * server has already accepted the notification, so parsing must never turn a
     * successful send into a fatal error for the caller.
     *
     * @param array<string, mixed>|null $response
     */
    public static function from(?array $response): self
    {
        $response ??= [];

        return new self(
            id: $response['id'] ?? '',
            event: $response['event'] ?? '',
            status: $response['status'] ?? '',
            error: $response['error'] ?? null,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === 'accepted';
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
