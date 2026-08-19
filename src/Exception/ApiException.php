<?php

namespace Sendivent\Exception;

use Throwable;

/**
 * The API answered with a non-2xx status.
 *
 * Carries the status code and decoded error body so callers can branch on
 * getStatusCode() (401 = bad key, 402 = quota exhausted, 422 = bad payload)
 * instead of pattern-matching an error message.
 */
class ApiException extends SendiventException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?string $errorCode = null,
        private readonly string $responseBody = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Machine-readable error code from the API, when it supplied one */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Raw response body, for logging */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
