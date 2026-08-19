<?php

namespace Sendivent\Exception;

/**
 * The request never produced an HTTP response — DNS failure, refused
 * connection, TLS handshake error or timeout.
 *
 * The notification may or may not have reached Sendivent; retry with an
 * idempotency key if you need certainty.
 */
class TransportException extends SendiventException
{
}
