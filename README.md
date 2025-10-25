# Sendivent PHP SDK

[![Latest Version](https://img.shields.io/packagist/v/sendivent/sdk.svg)](https://packagist.org/packages/sendivent/sdk)
[![License](https://img.shields.io/packagist/l/sendivent/sdk.svg)](https://packagist.org/packages/sendivent/sdk)

Official PHP SDK for [Sendivent](https://sendivent.com) - Multi-channel notification platform supporting Email, SMS, Slack, and Push notifications.

## Installation

```bash
composer require sendivent/sdk
```

Requires PHP 8.1+ and Guzzle 7.0+

## Quick Start

```php
use Sendivent\Sendivent;

$sendivent = new Sendivent('test_your_api_key_here', 'welcome');

$sendivent
    ->to('user@example.com')
    ->payload(['name' => 'John Doe'])
    ->send();
```

The SDK automatically routes to sandbox (`test_*`) or production (`live_*`) based on your API key prefix.

## Response Object

The `send()` method returns a `SendResponse` object with helper methods:

```php
$response = $sendivent
    ->to('user@example.com')
    ->payload(['name' => 'John'])
    ->send();

if ($response->isSuccess()) {
    echo "Sent! Queue IDs: " . json_encode($response->data);
} else {
    echo "Error: " . $response->error;
}

// Available properties: success, data, error, message
// Available methods: isSuccess(), hasError(), toArray(), toJson()
```

## Fire-and-Forget

For background sending without waiting for the response:

```php
$promise = $sendivent
    ->to('user@example.com')
    ->payload(['name' => 'John'])
    ->sendAsync();

// Continue with other work...
// Promise resolves in background
```

## Contact Objects

The `to()` method accepts strings, Contact objects, or arrays of either. The `id` field represents your application's user ID - you can pass your existing user objects directly:

```php
$sendivent
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

// Or multiple recipients
$sendivent
    ->to([
        'user1@example.com',
        ['id' => 'user-456', 'email' => 'user2@example.com', 'name' => 'Jane']
    ])
    ->payload(['subject' => 'Newsletter'])
    ->send();
```

## Key Features

- **Multi-channel** - Email, SMS, Slack, and Push in one API
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
    ->channel('sms')
    ->to('+1234567890')
    ->payload(['code' => '123456'])
    ->send();
```

### Template Overrides

```php
$sendivent
    ->to('user@example.com')
    ->payload(['amount' => 100])
    ->overrides([
        'subject' => 'Custom Subject',
        'from_email' => 'billing@company.com'
    ])
    ->send();
```

### Idempotency

```php
$sendivent
    ->to('user@example.com')
    ->payload(['order_id' => '12345'])
    ->idempotencyKey('order-12345-confirmation')
    ->send();
```

### Language Selection

```php
$sendivent
    ->to('user@example.com')
    ->payload(['name' => 'Anders'])
    ->language('sv')  // Swedish
    ->send();
```

### Broadcast Events

Send to configured event listeners without specifying recipients:

```php
$sendivent
    ->payload(['severity' => 'high', 'message' => 'System alert'])
    ->send();
```

## Full Example

See [example.php](./example.php) for a comprehensive demonstration of all SDK features.

## Support

- **Documentation:** [docs.sendivent.com](https://docs.sendivent.com)
- **Issues:** [github.com/sendivent/sdk-php/issues](https://github.com/sendivent/sdk-php/issues)

## License

MIT License - see [LICENSE](./LICENSE) file for details.
