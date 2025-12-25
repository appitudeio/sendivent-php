<?php

namespace Sendivent;

class SendResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?array $data = null,
        public readonly ?string $error = null,
        public readonly ?string $message = null,
    ) {}

    public static function from(array $response): self
    {
        return new self(
            success: $response['success'],
            data: $response['deliveries'] ?? null,
            error: $response['error'] ?? null,
            message: $response['message'] ?? null,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'message' => $this->message,
        ], fn($v) => $v !== null);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
