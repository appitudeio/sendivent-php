<?php

namespace Sendivent\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sendivent\Exception\ApiException;
use Sendivent\Exception\TransportException;
use Sendivent\Sendivent;

class SendiventTest extends TestCase
{
    /** @param list<Response|\Throwable> $queue */
    private function client(array $queue): Sendivent
    {
        $handler = HandlerStack::create(new MockHandler($queue));

        return new Sendivent('test_key', new Client([
            'handler' => $handler,
            'http_errors' => true,
        ]));
    }

    public function testRejectsAnUnprefixedApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Sendivent('nope');
    }

    public function testRequiresAnEvent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client([new Response(200, [], '{}')])->payload([])->send();
    }

    public function testReturnsTheAcceptedResponse(): void
    {
        $body = '{"id":"dd736a93-0031-4969-94c1-66e455ff1bbd","event":"receipt","status":"accepted"}';

        $response = $this->client([new Response(200, [], $body)])
            ->event('receipt')
            ->to('user@example.com')
            ->send();

        $this->assertTrue($response->isSuccess());
        $this->assertSame('receipt', $response->event);
    }

    /**
     * Regression: a 2xx means the notification was accepted. An unparseable
     * body must degrade to a non-success response, never throw — otherwise a
     * committed transaction dies on the receipt it already sent.
     *
     * @dataProvider unparseableBodies
     */
    public function testAnUnparseableBodyDoesNotThrow(string $body): void
    {
        $response = $this->client([new Response(200, [], $body)])->event('receipt')->send();

        $this->assertFalse($response->isSuccess());
        $this->assertSame('', $response->id);
    }

    /** @return array<string, array{0: string}> */
    public static function unparseableBodies(): array
    {
        return [
            'empty body' => [''],
            'html error page' => ['<html><body>502 Bad Gateway</body></html>'],
            'truncated json' => ['{"id":"abc","even'],
            'json scalar' => ['"accepted"'],
        ];
    }

    public function testHttpErrorBecomesApiExceptionWithStatusAndCode(): void
    {
        $failure = new RequestException(
            'Client error',
            new Request('POST', 'v1/send/receipt'),
            new Response(402, [], '{"error":"Monthly quota exhausted","code":"quota_exceeded"}')
        );

        try {
            $this->client([$failure])->event('receipt')->send();
            $this->fail('Expected an ApiException');
        } catch (ApiException $e) {
            $this->assertSame(402, $e->getStatusCode());
            $this->assertSame('quota_exceeded', $e->getErrorCode());
            $this->assertStringContainsString('Monthly quota exhausted', $e->getMessage());
            $this->assertStringContainsString('quota_exceeded', $e->getResponseBody());
        }
    }

    public function testApiExceptionHandlesTheNestedErrorShape(): void
    {
        $failure = new RequestException(
            'Client error',
            new Request('POST', 'v1/send/receipt'),
            new Response(422, [], '{"error":{"message":"Unknown event","code":"event_not_found"}}')
        );

        try {
            $this->client([$failure])->event('receipt')->send();
            $this->fail('Expected an ApiException');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('event_not_found', $e->getErrorCode());
            $this->assertStringContainsString('Unknown event', $e->getMessage());
        }
    }

    public function testApiExceptionSurvivesANonJsonErrorBody(): void
    {
        $failure = new RequestException(
            'Server error',
            new Request('POST', 'v1/send/receipt'),
            new Response(502, [], '<html>502</html>')
        );

        try {
            $this->client([$failure])->event('receipt')->send();
            $this->fail('Expected an ApiException');
        } catch (ApiException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertNull($e->getErrorCode());
        }
    }

    public function testConnectionFailureBecomesTransportException(): void
    {
        $failure = new ConnectException('cURL error 28: Operation timed out', new Request('POST', 'v1/send/receipt'));

        $this->expectException(TransportException::class);
        $this->client([$failure])->event('receipt')->send();
    }

    /** Both new exceptions stay catchable as RuntimeException for existing callers */
    public function testExceptionsRemainRuntimeExceptions(): void
    {
        $failure = new ConnectException('boom', new Request('POST', 'v1/send/receipt'));

        $this->expectException(\RuntimeException::class);
        $this->client([$failure])->event('receipt')->send();
    }

    public function testUserAgentTracksTheSdkVersion(): void
    {
        $this->assertSame('Sendivent-PHP/' . Sendivent::VERSION, Sendivent::userAgent());
    }
}
