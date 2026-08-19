# Sendivent PHP SDK

[![Latest Version](https://img.shields.io/packagist/v/sendivent/sdk.svg)](https://packagist.org/packages/sendivent/sdk)
[![License](https://img.shields.io/packagist/l/sendivent/sdk.svg)](https://packagist.org/packages/sendivent/sdk)

Official PHP SDK for [Sendivent](https://sendivent.com) - Multi-channel notification platform supporting Email, SMS, Slack, Push, Telegram, WhatsApp, and Discord.

## Installation

```bash
composer require sendivent/sdk
```

Requires PHP 8.1+ and Guzzle 7.0+

## Quick Start

```php
use Sendivent\Sendivent;

$sendivent = new Sendivent('test_your_api_key_here');

$sendivent
    ->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'John Doe'])
    ->send();
```

The SDK automatically routes to sandbox (`test_*`) or production (`live_*`) based on your API key prefix.

## Response Object

The `send()` method returns a `SendResponse` object with helper methods:

```php
$response = $sendivent
    ->event('invoice')
    ->to('user@example.com')
    ->payload(['amount' => 100])
    ->send();

if ($response->isSuccess()) {
    echo $response->id;
    // "550e8400-e29b-41d4-a716-446655440000"
} else {
    echo "Error: " . $response->error;
}

// Available properties: id, event, status, error
// Available methods: isSuccess(), hasError(), toArray(), toJson()
```

The `id` is the notification identifier. Notifications are processed asynchronously — use `GET /v1/notifications/{id}` to track message status.

## Fire-and-Forget

For background sending without waiting for the response:

```php
$promise = $sendivent
    ->event('notification')
    ->to('user@example.com')
    ->payload(['message' => 'Hello'])
    ->sendAsync();

// Continue with other work...
// Promise resolves in background
```

## Contact Objects & Smart Detection

The `to()` method accepts strings, Contact objects, or arrays of either. Sendivent automatically detects what type of identifier you're sending:

```php
// String inputs - automatically detected by pattern matching
$sendivent->event('welcome')->to('user@example.com')->send();  // Detected as email
$sendivent->event('sms-code')->to('+1234567890')->send();      // Detected as phone
$sendivent->event('alert')->to('U12345')->send();              // Detected as Slack user ID

// Contact objects - your user's ID maps to external_id in Sendivent
$sendivent
    ->event('welcome')
    ->to([
        'id' => 'user-12345',           // Your user's ID
        'email' => 'user@example.com',
        'phone' => '+1234567890',
        'name' => 'John Doe',
        'avatar' => 'https://example.com/avatar.jpg',
        'meta' => ['tier' => 'premium']
    ])
    ->payload(['welcome_message' => 'Hello!'])
    ->send();

// Multiple recipients
$sendivent
    ->event('newsletter')
    ->to([
        'user1@example.com',
        ['id' => 'user-456', 'email' => 'user2@example.com', 'name' => 'Jane']
    ])
    ->payload(['subject' => 'Monthly Update'])
    ->send();

// Broadcast to Slack channel (no contact created)
$sendivent
    ->event('system-alert')
    ->channel('slack')
    ->to('#general')  // Broadcasts to channel, doesn't create contact
    ->payload(['message' => 'System update'])
    ->send();
```

## Key Features

- **Multi-channel** - Email, SMS, Slack, Push, Telegram, WhatsApp, and Discord in one API
- **Fluent API** - Clean, chainable method calls
- **Type-safe** - Full PHP 8.1+ type hints
- **Fire-and-forget** - Async sending with `sendAsync()`
- **Idempotency** - Prevent duplicate sends with `idempotencyKey()`
- **Template overrides** - Customize subject, sender, etc. per request
- **Language support** - Send in different languages with `language()`
- **Channel control** - Force specific channels with `channel()`
- **Broadcast mode** - Send to event listeners without specifying recipients

## Additional Examples

### Channel-Specific Sending

```php
$sendivent
    ->event('verification-code')
    ->channel('sms')
    ->to('+1234567890')
    ->payload(['code' => '123456'])
    ->send();
```

### Template Overrides

```php
$sendivent
    ->event('invoice')
    ->to('user@example.com')
    ->payload(['amount' => 100])
    ->overrides([
        'email' => [
            'subject' => 'Custom Subject',
            'reply_to' => 'billing@company.com'
        ]
    ])
    ->send();
```

### Brand Overrides

```php
$sendivent
    ->event('welcome')
    ->to('user@example.com')
    ->overrides([
        'brand' => ['logotype' => 'https://example.fi/logo.png']
    ])
    ->send();
```

### Idempotency

```php
$sendivent
    ->event('order-confirmation')
    ->to('user@example.com')
    ->payload(['order_id' => '12345'])
    ->idempotencyKey('order-12345-confirmation')
    ->send();
```

### Language Selection

```php
$sendivent
    ->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'Anders'])
    ->language('sv')  // Swedish
    ->send();
```

### Broadcast Events

Send to configured event listeners without specifying recipients:

```php
$sendivent
    ->event('system-alert')
    ->payload(['severity' => 'high', 'message' => 'System alert'])
    ->send();
```

## Error Handling

`send()` throws only when the request fails. A 2xx response is always parsed
into a `SendResponse`, even if the body is unexpected — the notification was
already accepted at that point, so parsing never throws.

```php
use Sendivent\Exception\ApiException;
use Sendivent\Exception\TransportException;

try {
    $response = $sendivent->event('receipt')->to($email)->send();
}
catch (ApiException $e) {
    // The API answered with a non-2xx status
    if ($e->getStatusCode() === 402) {
        // Quota exhausted
    }

    error_log($e->getErrorCode() . ': ' . $e->getResponseBody());
}
catch (TransportException $e) {
    // Never reached the API — DNS, refused connection, TLS or timeout.
    // The notification may or may not have been delivered; retry with
    // idempotencyKey() if you need certainty.
}
```

Both extend `Sendivent\Exception\SendiventException`, which extends
`\RuntimeException` — existing `catch (\RuntimeException $e)` blocks keep working.

**Sending from inside a transaction?** A notification is rarely worth failing the
work that triggered it. Either catch `SendiventException` around the send, or use
`sendAsync()`, which is fire-and-forget and does not wait for a response.

## Development

```bash
composer install
composer test
```

## Full Example

See [example.php](./example.php) for a comprehensive demonstration of all SDK features.

## Support

- **Documentation:** [docs.sendivent.com](https://docs.sendivent.com)
- **Issues:** [github.com/sendivent/sdk-php/issues](https://github.com/sendivent/sdk-php/issues)

## License

MIT License - see [LICENSE](./LICENSE) file for details.
