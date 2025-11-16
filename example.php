<?php

/**
 * Sendivent PHP SDK - Focused Examples
 *
 * Demonstrates real-world use cases for multi-channel notifications
 * Run with: php example.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Sendivent\Sendivent;

// Initialize with your API key (test_ for sandbox, live_ for production)
$sendivent = new Sendivent('test_your_api_key_here');

// =============================================================================
// EXAMPLE 1: Smart Pattern Detection
// =============================================================================
echo "1. Smart Pattern Detection\n";

// Email - automatically detected
$sendivent
    ->event('welcome')
    ->to('user@example.com')
    ->payload(['name' => 'John Doe'])
    ->send();

// Phone - automatically detected
$sendivent
    ->event('verification-code')
    ->to('+1234567890')
    ->payload(['code' => '123456'])
    ->send();

// Slack User ID - automatically detected
$sendivent
    ->event('dm-notification')
    ->to('U12345ABCDE')
    ->payload(['message' => 'You have a new message'])
    ->send();

// =============================================================================
// EXAMPLE 2: Rich Contact Objects (CRM Tracking)
// =============================================================================
echo "\n2. Rich Contact Objects\n";

// Pass your user object directly - 'id' maps to external_id in Sendivent
$sendivent
    ->event('order-confirmation')
    ->to([
        'id' => 'user-12345',           // Your application's user ID
        'email' => 'customer@example.com',
        'phone' => '+1234567890',
        'name' => 'Jane Smith',
        'avatar' => 'https://example.com/avatar.jpg',
        'meta' => [
            'tier' => 'premium',
            'timezone' => 'America/New_York'
        ]
    ])
    ->payload([
        'order_id' => 'ORD-98765',
        'total' => 99.99,
        'items' => [
            ['name' => 'Product A', 'qty' => 2],
            ['name' => 'Product B', 'qty' => 1]
        ]
    ])
    ->send();

// =============================================================================
// EXAMPLE 3: Broadcast Mode (Event Listeners)
// =============================================================================
echo "\n3. Broadcast Mode\n";

// Send to all configured event listeners (no recipients specified)
$sendivent
    ->event('system-alert')
    ->payload([
        'severity' => 'high',
        'message' => 'Database backup completed',
        'timestamp' => time()
    ])
    ->send();

// =============================================================================
// EXAMPLE 4: Slack Channel Broadcasting (No Contact Created)
// =============================================================================
echo "\n4. Slack Channel Broadcasting\n";

// Broadcast to Slack channel - doesn't create a contact in CRM
$sendivent
    ->event('team-announcement')
    ->channel('slack')
    ->to('#general')  // Channel name
    ->payload([
        'title' => 'Weekly Update',
        'message' => 'New features released this week!'
    ])
    ->send();

// Or use channel ID
$sendivent
    ->event('team-announcement')
    ->channel('slack')
    ->to('C01234ABCDE')  // Channel ID
    ->payload([
        'title' => 'Weekly Update',
        'message' => 'New features released!'
    ])
    ->send();

// =============================================================================
// EXAMPLE 5: Channel-Specific Sending
// =============================================================================
echo "\n5. Channel-Specific Sending\n";

// Force SMS even if event supports multiple channels
$sendivent
    ->event('urgent-alert')
    ->channel('sms')
    ->to('+1234567890')
    ->payload(['alert' => 'Your account requires attention'])
    ->send();

// Force Email
$sendivent
    ->event('monthly-report')
    ->channel('email')
    ->to('manager@example.com')
    ->payload([
        'month' => 'January',
        'revenue' => 125000,
        'growth' => 15.5
    ])
    ->send();

// =============================================================================
// EXAMPLE 6: Multiple Recipients (Bulk Sending)
// =============================================================================
echo "\n6. Multiple Recipients\n";

$sendivent
    ->event('newsletter')
    ->to([
        'subscriber1@example.com',
        'subscriber2@example.com',
        ['email' => 'vip@example.com', 'name' => 'VIP Customer', 'meta' => ['tier' => 'platinum']]
    ])
    ->payload([
        'subject' => 'Monthly Newsletter',
        'featured_article' => 'Top 10 Features You Might Have Missed'
    ])
    ->send();

// =============================================================================
// EXAMPLE 7: Language Selection
// =============================================================================
echo "\n7. Language Selection\n";

// Send in Swedish
$sendivent
    ->event('welcome')
    ->to('anders@example.com')
    ->payload(['name' => 'Anders'])
    ->language('sv')
    ->send();

// Send in Spanish
$sendivent
    ->event('password-reset')
    ->to('maria@example.com')
    ->payload(['reset_link' => 'https://app.example.com/reset/xyz'])
    ->language('es')
    ->send();

// =============================================================================
// EXAMPLE 8: Template Overrides
// =============================================================================
echo "\n8. Template Overrides\n";

// Override subject and sender for this specific send
$sendivent
    ->event('invoice')
    ->to('customer@example.com')
    ->payload([
        'invoice_number' => 'INV-2024-001',
        'amount' => 499.99
    ])
    ->overrides([
        'subject' => 'URGENT: Invoice Due',
        'from_email' => 'billing@example.com',
        'from_name' => 'Billing Department'
    ])
    ->send();

// =============================================================================
// EXAMPLE 9: Idempotency (Prevent Duplicate Sends)
// =============================================================================
echo "\n9. Idempotency\n";

// Use idempotency key to prevent duplicate sends
$orderId = 'ORD-12345';

$sendivent
    ->event('order-confirmation')
    ->to('customer@example.com')
    ->payload([
        'order_id' => $orderId,
        'total' => 299.99
    ])
    ->idempotencyKey("order-{$orderId}-confirmation")
    ->send();

// If called again with same key within TTL, won't send duplicate
$sendivent
    ->event('order-confirmation')
    ->to('customer@example.com')
    ->payload([
        'order_id' => $orderId,
        'total' => 299.99
    ])
    ->idempotencyKey("order-{$orderId}-confirmation")  // Same key = no duplicate
    ->send();

// =============================================================================
// EXAMPLE 10: Fire-and-Forget (Async Sending)
// =============================================================================
echo "\n10. Fire-and-Forget\n";

// Send without waiting for response (faster, non-blocking)
$sendivent
    ->event('analytics-event')
    ->to('user@example.com')
    ->payload([
        'event' => 'page_view',
        'page' => '/dashboard',
        'timestamp' => time()
    ])
    ->sendAsync();

echo "Message sent in background!\n";

// =============================================================================
// EXAMPLE 11: Error Handling
// =============================================================================
echo "\n11. Error Handling\n";

try {
    $response = $sendivent
        ->event('test-event')
        ->to('test@example.com')
        ->payload(['test' => true])
        ->send();

    if ($response->isSuccess()) {
        echo "✓ Success! Queue IDs: " . json_encode($response->data) . "\n";
    } else {
        echo "✗ Failed: " . $response->error . "\n";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// =============================================================================
// EXAMPLE 12: Smart Fallback (Priority-Based Channel Selection)
// =============================================================================
echo "\n12. Smart Fallback\n";

// Contact has no email but has Slack ID
// Event configured for email+slack
// System automatically sends via Slack (priority fallback)
$sendivent
    ->event('notification')
    ->to([
        'id' => 'user-789',
        'slack_id' => 'U98765ZYXWV',  // Has Slack
        // No email - will automatically use Slack
        'name' => 'Bob'
    ])
    ->payload(['message' => 'Your report is ready'])
    ->send();

echo "\n✓ All examples completed!\n";
