<?php
/**
 * Live smoke test — the only check that proves the DEPLOYED API still matches
 * what the SDK expects. Unit tests use a mocked transport, so they cannot
 * catch the API reshaping its response (which is exactly what broke v0.2.1).
 *
 * Opt-in; skipped unless a sandbox key is present:
 *
 *   SENDIVENT_TEST_KEY=test_... SENDIVENT_TEST_EVENT=onboarding_welcome \
 *     php tests/smoke/live.php
 *
 * Sends to a reserved, non-deliverable address so nobody receives mail.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Sendivent\Sendivent;
use Sendivent\Exception\ApiException;

$key = getenv('SENDIVENT_TEST_KEY') ?: '';
$event = getenv('SENDIVENT_TEST_EVENT') ?: '';

if ($key === '' || $event === '') {
    fwrite(STDERR, "SKIP: set SENDIVENT_TEST_KEY and SENDIVENT_TEST_EVENT\n");
    exit(0);
}

if (!str_starts_with($key, 'test_')) {
    fwrite(STDERR, "REFUSED: smoke tests only run against a sandbox (test_) key\n");
    exit(1);
}

$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) { $failures++; }
    printf("  %s %-46s %s\n", $ok ? 'PASS' : 'FAIL', $label, $detail);
}

echo "Live contract check against api-sandbox.sendivent.com\n\n";

// 1. The success contract
$response = (new Sendivent($key))
    ->event($event)
    ->to('sdk-smoke-test@example.com')
    ->payload(['source' => 'sdk-smoke-test'])
    ->send();

check('send() returns a parsed response', $response->isSuccess(), 'status=' . $response->status);
check('status is "accepted"', $response->status === 'accepted');
check('id is a populated UUID', (bool) preg_match('/^[0-9a-f-]{36}$/i', $response->id), $response->id);
check('event echoes the request', $response->event === $event, $response->event);
check('no error on the success path', !$response->hasError());

// 2. The error contract — a bad key must surface as a typed 401
try {
    (new Sendivent('test_' . str_repeat('0', 32)))->event($event)->to('x@example.com')->send();
    check('invalid key raises ApiException', false, 'no exception thrown');
} catch (ApiException $e) {
    check('invalid key raises ApiException', true);
    check('  carries HTTP status', $e->getStatusCode() === 401, 'status=' . $e->getStatusCode());
    check('  carries the API message', str_contains($e->getMessage(), 'Invalid API key'), $e->getMessage());
}

echo "\n" . ($failures === 0 ? "All contract checks passed.\n" : "$failures check(s) FAILED.\n");
exit($failures === 0 ? 0 : 1);
