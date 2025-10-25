<?php

/**
 * Sendivent PHP SDK - Comprehensive Example
 *
 * This file demonstrates all SDK features with inline comments.
 * Replace 'test_your_api_key_here' with your actual API key to run.
 */

require 'vendor/autoload.php';

use Sendivent\Sendivent;
use GuzzleHttp\Exception\GuzzleException;

// ============================================================================
// BASIC USAGE
// ============================================================================

// Initialize with your API key and event name
// API key prefix determines environment: test_* = sandbox, live_* = production
$sendivent = new Sendivent('test_your_api_key_here', 'welcome');

// Simple send to an email address
$sendivent
    ->to('user@example.com')
    ->payload(['name' => 'John Doe', 'company' => 'Acme Corp'])
    ->send();

// ============================================================================
// RESPONSE HANDLING
// ============================================================================

// The send() method returns a SendResponse object with helper methods
$response = $sendivent
    ->to('user@example.com')
    ->payload(['name' => 'Jane'])
    ->send();

// Check success/error using helper methods
if ($response->isSuccess()) {
    echo "✓ Notification sent successfully!\n";
    echo "Queue IDs: " . json_encode($response->data) . "\n";
} else {
    echo "✗ Error: " . $response->error . "\n";
}

// Access response properties directly
echo "Success: " . ($response->success ? 'true' : 'false') . "\n";
echo "Message: " . ($response->message ?? 'none') . "\n";

// Convert to array or JSON
$responseArray = $response->toArray();
$responseJson = $response->toJson();

// ============================================================================
// FIRE-AND-FORGET (ASYNC)
// ============================================================================

// For background sending without waiting for the response
$promise = $sendivent
    ->to('user@example.com')
    ->payload(['name' => 'Background User'])
    ->sendAsync();

// Continue with other work immediately...
echo "Async send initiated, continuing with other work...\n";

// Promise will resolve in background

// ============================================================================
// CONTACT OBJECTS
// ============================================================================

// The 'id' field represents your application's user ID - pass your user objects directly!
// Sendivent will map this to internal identifiers for template usage
$sendivent
    ->to([
        'id' => 'user-12345',           // Your application's user ID
        'email' => 'user@example.com',
        'phone' => '+1234567890',
        'name' => 'John Doe',
        'avatar' => 'https://example.com/avatar.jpg',
        'meta' => [
            'tier' => 'premium',
            'department' => 'Engineering',
            'timezone' => 'America/New_York'
        ]
    ])
    ->payload(['welcome_message' => 'Welcome to our platform!'])
    ->send();

// ============================================================================
// MULTIPLE RECIPIENTS
// ============================================================================

// Send to multiple recipients in one call
$sendivent
    ->to([
        'user1@example.com',
        'user2@example.com',
        ['id' => 'user-456', 'email' => 'user3@example.com', 'name' => 'User Three']
    ])
    ->payload(['subject' => 'Monthly Newsletter', 'content' => '...'})
    ->send();

// ============================================================================
// CHANNEL-SPECIFIC SENDING
// ============================================================================

// Force a specific channel (email, sms, slack, push)
$sendivent = new Sendivent('test_your_api_key_here', 'password-reset');
$sendivent
    ->channel('email')
    ->to('user@example.com')
    ->payload(['reset_link' => 'https://example.com/reset/abc123'])
    ->send();

// SMS example
$sendivent = new Sendivent('test_your_api_key_here', 'verification-code');
$sendivent
    ->channel('sms')
    ->to('+1234567890')
    ->payload(['code' => '123456'])
    ->send();

// ============================================================================
// TEMPLATE OVERRIDES
// ============================================================================

// Override template defaults like subject, sender, etc.
$sendivent = new Sendivent('test_your_api_key_here', 'invoice');
$sendivent
    ->to('user@example.com')
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
// Sending multiple times with the same key returns cached response without re-sending
$sendivent = new Sendivent('test_your_api_key_here', 'order-confirmation');
$sendivent
    ->to('user@example.com')
    ->payload(['order_id' => '12345', 'total' => 99.99])
    ->idempotencyKey('order-12345-confirmation')
    ->send();

// Sending again with same key won't send duplicate notification
$sendivent
    ->to('user@example.com')
    ->payload(['order_id' => '12345', 'total' => 99.99])
    ->idempotencyKey('order-12345-confirmation')
    ->send(); // Returns cached response

// ============================================================================
// LANGUAGE SELECTION
// ============================================================================

// Send notifications in different languages
$sendivent = new Sendivent('test_your_api_key_here', 'welcome');
$sendivent
    ->to('user@example.com')
    ->payload(['name' => 'Anders'])
    ->language('sv')  // Swedish
    ->send();

// ============================================================================
// BROADCAST EVENTS
// ============================================================================

// Send to configured event listeners without specifying recipients
$sendivent = new Sendivent('test_your_api_key_here', 'system-alert');
$sendivent
    ->payload([
        'severity' => 'high',
        'message' => 'Database backup completed successfully',
        'timestamp' => date('c')
    ])
    ->send();

// ============================================================================
// ERROR HANDLING
// ============================================================================

try {
    // Invalid API key format will throw InvalidArgumentException
    $sendivent = new Sendivent('invalid_key', 'test-event');
} catch (\InvalidArgumentException $e) {
    echo "Invalid API key format: " . $e->getMessage() . "\n";
}

try {
    $sendivent = new Sendivent('test_your_api_key_here', 'test-event');

    $response = $sendivent
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
} catch (GuzzleException $e) {
    // Network/HTTP errors
    echo "Network error: " . $e->getMessage() . "\n";
}
