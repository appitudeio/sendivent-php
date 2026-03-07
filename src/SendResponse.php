<?php

namespace Sendivent;

class SendResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?array $deliveries = null,
        public readonly ?string $error = null,
    ) {}

    public static function from(array $response): self
    {
        return new self(
            success: $response['success'],
            deliveries: $response['deliveries'] ?? null,
            error: $response['error'] ?? null,
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
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
