<?php

require_once __DIR__ . '/vendor/autoload.php';

use Sendivent\Sendivent;

/**
 * Simple local test script for Sendivent PHP SDK
 *
 * Usage:
 *   SENDIVENT_API_KEY=test_your_key_here php test-local.php
 *
 * Or edit the $apiKey variable below directly.
 */

// Get API key from environment or set directly here for testing
$apiKey = getenv('SENDIVENT_API_KEY') ?: 'test_b9c2d76285130dac584c55f63225cd35b51ee7875786df6e170d494d7c70b97f';

if ($apiKey === 'test_your_sandbox_key_here') {
    echo "⚠️  Please set your Sendivent sandbox API key:\n";
    echo "   SENDIVENT_API_KEY=test_xxxxx php test-local.php\n";
    echo "   OR edit the \$apiKey variable in this script\n\n";
    exit(1);
}

echo "🚀 Testing Sendivent PHP SDK\n";
echo "================================\n\n";

// Initialize the SDK
echo "1. Initializing Sendivent client...\n";
$sendivent = new Sendivent($apiKey);
echo "   ✓ Client initialized\n";
echo "   Environment: " . (str_starts_with($apiKey, 'test_') ? 'SANDBOX' : 'PRODUCTION') . "\n\n";

// Test: Send notifications asynchronously (fire-and-forget)
echo "2. Sending async notifications (fire-and-forget)...\n";
echo "   Using fsockopen - requests sent immediately without blocking.\n\n";

$startAsync = microtime(true);

try {
    // Send async requests - each sends immediately and returns
    echo "   Sending email... ";
    $sendivent
        ->event('welcome')
        ->to('mathiaseklof@gmail.com')
        ->sendAsync();
    echo "✓\n";

    echo "   Sending SMS... ";
    $sendivent
        ->event('welcome')
        ->to('mathiaseklof@gmail.com')
        ->channel('sms')
        ->sendAsync();
    echo "✓\n";

    echo "   Sending Slack... ";
    $sendivent
        ->event('welcome')
        ->to('mathiaseklof@gmail.com')
        ->channel('slack')
        ->sendAsync();
    echo "✓\n";

    $asyncDuration = microtime(true) - $startAsync;
    echo sprintf("\n   ✓ All requests sent in %.3fs (true fire-and-forget!)\n", $asyncDuration);
    echo "   Note: Requests were sent immediately without waiting for responses.\n";

} catch (Exception $e) {
    echo "\n❌ Exception occurred during async sending:\n";
    echo "   " . $e->getMessage() . "\n";
}

echo "================================\n";
echo "✅ All SDK tests completed!\n";
echo "   Check your Sendivent dashboard to see the queued notifications.\n";
