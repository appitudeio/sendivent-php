<?php

/**
 * Sendivent PHP SDK - Comprehensive Example
 *
 * This file demonstrates all SDK features with practical, working examples.
 * Replace 'test_your_api_key_here' with your actual API key to run.
 */

require 'vendor/autoload.php';

use Sendivent\Sendivent;

// ============================================================================
// BASIC USAGE
// ============================================================================

// The SDK is per-event: create one instance per event type
// Constructor: new Sendivent(apiKey, eventName)
// API key prefix determines environment: test_* = sandbox, live_* = production

$welcome = new Sendivent('test_your_api_key_here', 'welcome');

// Simple send to an email address
$welcome
    ->to('user@example.com')
    ->payload(['name' => 'John Doe', 'company' => 'Acme Corp'])
    ->send();

// ============================================================================
// RESPONSE HANDLING
// ============================================================================

// The send() method returns a SendResponse object with helper methods
$response = $welcome
    ->to('jane@example.com')
    ->payload(['name' => 'Jane Smith'])
    ->send();

// Check success/error using helper methods
if ($response->isSuccess()) {
    echo "✓ Notification sent successfully!\n";
    echo "Queue IDs: " . json_encode($response->data) . "\n";
} else {
    echo "✗ Error: " . $response->error . "\n";
}

// Access response properties directly (all readonly)
echo "Success: " . ($response->success ? 'true' : 'false') . "\n";
echo "Message: " . ($response->message ?? 'none') . "\n";

// Convert to array or JSON
$responseArray = $response->toArray();
$responseJson = $response->toJson(JSON_PRETTY_PRINT);

// ============================================================================
// FIRE-AND-FORGET (ASYNC)
// ============================================================================

// For background sending without waiting for the response
// Returns a GuzzleHttp Promise that resolves in the background
$promise = $welcome
    ->to('background@example.com')
    ->payload(['name' => 'Background User'])
    ->sendAsync();

// Continue with other work immediately...
echo "Async send initiated, continuing with other work...\n";

// Optionally handle promise resolution (or just let it resolve in background)
$promise->then(
    function ($response) {
        echo "Async send completed!\n";
    },
    function ($error) {
        echo "Async send failed: " . $error->getMessage() . "\n";
    }
);

// ============================================================================
// CONTACT OBJECTS
// ============================================================================

// The 'id' field represents your application's user ID
// You can pass your existing user objects directly - Sendivent maps them internally
$welcome
    ->to([
        'id' => 'user-12345',           // Your application's user ID
        'email' => 'user@example.com',
        'phone' => '+1234567890',
        'name' => 'John Doe',
        'avatar' => 'https://example.com/avatar.jpg',
        'meta' => [
            'tier' => 'premium',
            'department' => 'Engineering'
        ]
    ])
    ->payload(['message' => 'Welcome to our platform!'])
    ->send();

// ============================================================================
// MULTIPLE RECIPIENTS
// ============================================================================

// Send to multiple recipients in one call
// Mix strings and contact objects in an array
$newsletter = new Sendivent('test_your_api_key_here', 'newsletter');

$newsletter
    ->to([
        'user1@example.com',
        'user2@example.com',
        ['id' => 'user-456', 'email' => 'user3@example.com', 'name' => 'User Three']
    ])
    ->payload(['subject' => 'Monthly Newsletter', 'edition' => 'May 2024'])
    ->send();

// ============================================================================
// CHANNEL-SPECIFIC SENDING
// ============================================================================

// Force a specific channel (email, sms, slack, push)
// Useful when you want to override the default channel selection

$passwordReset = new Sendivent('test_your_api_key_here', 'password-reset');
$passwordReset
    ->channel('email')
    ->to('user@example.com')
    ->payload(['reset_link' => 'https://example.com/reset/abc123'])
    ->send();

// SMS example
$verification = new Sendivent('test_your_api_key_here', 'verification-code');
$verification
    ->channel('sms')
    ->to('+1234567890')
    ->payload(['code' => '123456'])
    ->send();

// ============================================================================
// TEMPLATE OVERRIDES
// ============================================================================

// Override template defaults like subject, sender, etc. on a per-request basis
$invoice = new Sendivent('test_your_api_key_here', 'invoice');

$invoice
    ->to('customer@example.com')
    ->payload(['amount' => 100, 'invoice_id' => 'INV-001'])
    ->overrides([
        'subject' => 'Your Custom Invoice Subject',
        'from_email' => 'billing@company.com',
        'from_name' => 'Billing Department',
        'reply_to' => 'support@company.com'
    ])
    ->send();

// ============================================================================
// IDEMPOTENCY
// ============================================================================

// Prevent duplicate sends using idempotency keys
// The API caches responses for 24 hours based on the key
$orderConfirmation = new Sendivent('test_your_api_key_here', 'order-confirmation');

$orderConfirmation
    ->to('customer@example.com')
    ->payload(['order_id' => '12345', 'total' => 99.99])
    ->idempotencyKey('order-12345-confirmation')
    ->send();

// Sending again with same key returns cached response without re-sending
$orderConfirmation
    ->to('customer@example.com')
    ->payload(['order_id' => '12345', 'total' => 99.99])
    ->idempotencyKey('order-12345-confirmation')
    ->send(); // Returns cached response, no duplicate sent

// ============================================================================
// LANGUAGE SELECTION
// ============================================================================

// Send notifications in different languages (if your templates support it)
$welcome
    ->to('user@example.com')
    ->payload(['name' => 'Anders Andersson'])
    ->language('sv')  // Swedish
    ->send();

// ============================================================================
// BROADCAST EVENTS
// ============================================================================

// Send to configured event listeners without specifying recipients
// The 'to' parameter is optional - omit it to broadcast to event subscribers
$systemAlert = new Sendivent('test_your_api_key_here', 'system-alert');

$systemAlert
    ->payload([
        'severity' => 'high',
        'message' => 'Database backup completed successfully',
        'timestamp' => date('c')
    ])
    ->send(); // Note: no ->to() call

// ============================================================================
// MULTIPLE EVENTS WITH REUSABLE INSTANCES
// ============================================================================

// Create instances for different event types and reuse them
$orderEvents = new Sendivent('test_your_api_key_here', 'order-placed');
$paymentEvents = new Sendivent('test_your_api_key_here', 'payment-received');

// Send order notification
$orderEvents
    ->to('customer@example.com')
    ->payload(['order_id' => 'ORD-001', 'items' => 3])
    ->send();

// Send payment notification (different event, different instance)
$paymentEvents
    ->to('customer@example.com')
    ->payload(['amount' => 150.00, 'order_id' => 'ORD-001'])
    ->send();

// Reuse the same instance for another order
$orderEvents
    ->to('another@example.com')
    ->payload(['order_id' => 'ORD-002', 'items' => 5])
    ->send();

// ============================================================================
// ERROR HANDLING
// ============================================================================

try {
    // Invalid API key format throws InvalidArgumentException
    $invalid = new Sendivent('invalid_key', 'test-event');
} catch (\InvalidArgumentException $e) {
    echo "Invalid API key format: " . $e->getMessage() . "\n";
}

try {
    $test = new Sendivent('test_your_api_key_here', 'test-event');

    $response = $test
        ->to('user@example.com')
        ->payload(['test' => 'data'])
        ->send();

    // Always check response success
    if ($response->hasError()) {
        echo "Error occurred: " . $response->error . "\n";
    }

} catch (\RuntimeException $e) {
    // API request failures throw RuntimeException
    echo "API error: " . $e->getMessage() . "\n";
}
