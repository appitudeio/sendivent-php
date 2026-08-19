<?php

namespace Sendivent\Exception;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * Base class for every exception thrown by the SDK.
 *
 * Extends RuntimeException so existing `catch (\RuntimeException $e)` blocks
 * keep working across the upgrade.
 */
class SendiventException extends RuntimeException
{
    /**
     * Translate a Guzzle failure into the SDK's own exception hierarchy.
     *
     * A failure that carries an HTTP response becomes an ApiException with the
     * status code and decoded error body attached; anything else (DNS, refused
     * connection, TLS, timeout) becomes a TransportException.
     */
    public static function fromGuzzle(GuzzleException $e): self
    {
        if (!$e instanceof RequestException || !$e->hasResponse()) {
            return new TransportException(
                'Sendivent API request failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $response = $e->getResponse();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        $decoded = is_array($decoded) ? $decoded : [];

        $errorCode = is_string($decoded['code'] ?? null) ? $decoded['code'] : null;
        $error = $decoded['error'] ?? $decoded['message'] ?? null;

        // The API also uses the nested shape { error: { code, message } }
        if (is_array($error)) {
            $errorCode ??= is_string($error['code'] ?? null) ? $error['code'] : null;
            $error = $error['message'] ?? null;
        }

        $message = (is_string($error) && $error !== '') ? $error : $e->getMessage();

        return new ApiException(
            'Sendivent API error (HTTP ' . $response->getStatusCode() . '): ' . $message,
            $response->getStatusCode(),
            $errorCode,
            $body,
            $e
        );
    }
}
