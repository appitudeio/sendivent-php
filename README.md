# Sendivent PHP SDK

Official PHP SDK for [Sendivent](https://sendivent.com) - Multi-channel notification platform supporting Email, SMS, and Slack.

## Installation

```bash
composer require sendivent/sdk
```

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client 7.0+

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use Sendivent\Sendivent;

// Initialize with your API key
$sendivent = new Sendivent('test_your_api_key_here');

// Send a notification
$sendivent->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'John Doe', 'company' => 'Acme Corp'])
    ->send();
```

## API Key Modes

The SDK automatically detects the environment based on your API key prefix:

- `test_*` → Sandbox mode (`https://api-sandbox.sendivent.com`)
- `live_*` → Production mode (`https://api.sendivent.com`)

## Usage Examples

### Basic Notification

```php
$sendivent->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'John'])
    ->send();
```

### Get Response

```php
$response = $sendivent->event('invoice')
    ->to('user@example.com')
    ->payload(['amount' => 100, 'invoice_id' => '12345'])
    ->get();

// Response structure:
// [
//     'success' => true,
//     'data' => [
//         ['email' => 'uuid-of-queued-notification']
//     ]
// ]
```

### Channel-Specific Sending

Force a specific channel (email, sms, or slack):

```php
use Sendivent\Channel;

$sendivent->event('password-reset')
    ->channel(Channel::EMAIL)
    ->to('user@example.com')
    ->payload(['reset_link' => 'https://example.com/reset/abc123'])
    ->send();

// Or use string
$sendivent->event('alert')
    ->channel('sms')
    ->to('+1234567890')
    ->payload(['message' => 'Your code is 123456'])
    ->send();
```

### Broadcast Events (No Specific Recipient)

Send to event listeners without specifying recipients:

```php
$sendivent->event('system-alert')
    ->payload([
        'severity' => 'high',
        'message' => 'Database backup completed successfully'
    ])
    ->send();
```

### Multiple Recipients

```php
$sendivent->event('newsletter')
    ->to([
        'user1@example.com',
        'user2@example.com',
        ['email' => 'user3@example.com', 'name' => 'User Three']
    ])
    ->payload(['subject' => 'Monthly Newsletter', 'content' => '...'])
    ->send();
```

### With Language

```php
$sendivent->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'Anders'])
    ->language('sv')  // Swedish
    ->send();
```

### Template Overrides

Override template defaults like subject, sender, etc.:

```php
$sendivent->event('invoice')
    ->to('user@example.com')
    ->payload(['amount' => 100])
    ->overrides([
        'subject' => 'Your Custom Invoice Subject',
        'from_email' => 'billing@company.com',
        'from_name' => 'Billing Department',
        'reply_to' => 'support@company.com'
    ])
    ->send();
```

### Idempotency

Prevent duplicate sends using idempotency keys:

```php
$sendivent->event('order-confirmation')
    ->to('user@example.com')
    ->payload(['order_id' => '12345'])
    ->idempotencyKey('order-12345-confirmation')
    ->send();

// Sending again with same key returns cached response without re-sending
```

### Complex Contact Objects

```php
$sendivent->event('welcome')
    ->to([
        'email' => 'user@example.com',
        'phone' => '+1234567890',
        'name' => 'John Doe',
        'external_id' => 'user-12345',
        'meta' => [
            'department' => 'Engineering',
            'timezone' => 'America/New_York'
        ]
    ])
    ->payload(['welcome_message' => 'Welcome to our platform!'])
    ->send();
```

## API Reference

### `Sendivent`

#### `__construct(string $apiKey)`

Initialize the Sendivent client.

```php
$sendivent = new Sendivent('test_abc123');
```

#### `event(string $eventName): EventRequest`

Create a new event request builder.

```php
$request = $sendivent->event('welcome');
```

---

### `EventRequest`

Fluent builder for notification requests.

#### `to(string|array $recipient): self`

Set recipient(s). Can be:
- Email string: `'user@example.com'`
- Phone string: `'+1234567890'`
- Contact object: `['email' => '...', 'name' => '...']`
- Array of recipients: `['user1@example.com', 'user2@example.com']`

#### `payload(array $data): self`

Set template variables.

```php
->payload(['name' => 'John', 'amount' => 100])
```

#### `channel(Channel|string $channel): self`

Force specific channel. Use `Channel` enum or string.

```php
->channel(Channel::EMAIL)
->channel('sms')
```

#### `language(string $language): self`

Set language code (e.g., 'en', 'sv', 'da').

```php
->language('sv')
```

#### `overrides(array $overrides): self`

Override template defaults.

```php
->overrides(['subject' => 'Custom Subject'])
```

#### `idempotencyKey(string $key): self`

Set idempotency key for deduplication.

```php
->idempotencyKey('unique-key-123')
```

#### `send(): array`

Execute the request and return response.

```php
$response = $request->send();
```

#### `get(): array`

Alias for `send()`.

```php
$response = $request->get();
```

## Type Helpers

The SDK provides helper classes for working with types:

### ContactType

Helper for creating contact objects:

```php
use Sendivent\ContactType;

$contact = ContactType::create(
    email: 'user@example.com',
    name: 'John Doe',
    phone: '+1234567890',
    externalId: 'user-12345',
    meta: ['department' => 'Engineering']
);

$sendivent->event('welcome')
    ->to($contact)
    ->payload(['message' => 'Hello!'])
    ->send();
```

### SendResponseType

Helper for working with API responses:

```php
use Sendivent\SendResponseType;

$response = $sendivent->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'John'])
    ->send();

// Check if successful
if (SendResponseType::isSuccess($response)) {
    $data = SendResponseType::getData($response);
    echo "Sent successfully!\n";
} else {
    $error = SendResponseType::getError($response);
    echo "Error: $error\n";
}
```

### RecipientType

Helper for validating recipients:

```php
use Sendivent\RecipientType;

$email = 'user@example.com';
if (RecipientType::isValid($email)) {
    echo "Valid recipient\n";
}
```

## Error Handling

```php
use Sendivent\Sendivent;
use Sendivent\SendResponseType;

try {
    $sendivent = new Sendivent('test_abc123');

    $response = $sendivent->event('welcome')
        ->to('user@example.com')
        ->payload(['name' => 'John'])
        ->send();

    if (SendResponseType::isSuccess($response)) {
        echo "Notification sent successfully!\n";
    } else {
        echo "Failed: " . SendResponseType::getError($response) . "\n";
    }
} catch (\InvalidArgumentException $e) {
    // Invalid API key format
    echo "Invalid API key: " . $e->getMessage();
} catch (\RuntimeException $e) {
    // API request failed
    echo "API error: " . $e->getMessage();
}
```

## Support

- **Documentation:** [https://docs.sendivent.com](https://docs.sendivent.com)
- **Support:** support@sendivent.com
- **Issues:** [GitHub Issues](https://github.com/sendivent/sdk-php/issues)

## License

MIT License - see LICENSE file for details.
