<?php

namespace Sendivent\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sendivent\Exception\ApiException;
use Sendivent\Exception\TransportException;
use Sendivent\Sendivent;

class ContactsTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $sent = [];

    /** @param list<Response|\Throwable> $queue */
    private function contacts(array $queue): \Sendivent\Contacts
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::mapRequest(function (RequestInterface $r) {
            $this->sent[] = $r;
            return $r;
        }));

        return (new Sendivent('test_key', new Client([
            'handler' => $stack,
            'base_uri' => 'https://api-sandbox.sendivent.com/',
            'http_errors' => true,
        ])))->contacts();
    }

    private function lastRequest(): RequestInterface
    {
        return $this->sent[count($this->sent) - 1];
    }

    private function ok(array $body): Response
    {
        return new Response(200, [], json_encode($body));
    }

    public function testGetIssuesTheRightRequest(): void
    {
        $contacts = $this->contacts([$this->ok(['success' => true, 'contact' => ['uuid' => 'abc']])]);
        $result = $contacts->get('user@example.com');

        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame(
            '/v1/contacts/user%40example.com',
            $this->lastRequest()->getUri()->getPath(),
        );
        $this->assertSame(['success' => true, 'contact' => ['uuid' => 'abc']], $result);
    }

    /**
     * Plus-addressing is common and must survive path encoding — a raw `+` in
     * a path segment would be read as a literal plus by some proxies.
     */
    public function testIdentifiersAreEncodedIntoThePath(): void
    {
        $contacts = $this->contacts([$this->ok(['success' => true])]);
        $contacts->get('user+tag@example.com');

        $this->assertStringContainsString('user%2Btag%40example.com', $this->lastRequest()->getUri()->getPath());
    }

    public function testUpsertPostsTheBody(): void
    {
        $contacts = $this->contacts([$this->ok(['success' => true, 'contact' => []])]);
        $contacts->upsert(['email' => 'user@example.com', 'name' => 'Ada']);

        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/contacts', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(
            ['email' => 'user@example.com', 'name' => 'Ada'],
            json_decode((string) $this->lastRequest()->getBody(), true),
        );
    }

    public function testUpdateUsesPatch(): void
    {
        $contacts = $this->contacts([$this->ok(['success' => true, 'contact' => []])]);
        $contacts->update('user@example.com', ['name' => 'Grace']);

        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/contacts/user%40example.com', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['name' => 'Grace'], json_decode((string) $this->lastRequest()->getBody(), true));
    }

    public function testDeleteSendsNoBody(): void
    {
        $contacts = $this->contacts([$this->ok(['success' => true, 'deleted' => true])]);
        $result = $contacts->delete('user@example.com');

        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('', (string) $this->lastRequest()->getBody());
        $this->assertSame(['success' => true, 'deleted' => true], $result);
    }

    public function testPushTokenRoutes(): void
    {
        $contacts = $this->contacts([
            $this->ok(['success' => true, 'contact' => []]),
            $this->ok(['success' => true, 'contact' => []]),
        ]);

        $contacts->registerPushToken('user@example.com', 'tok-1');
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/contacts/user%40example.com/push-tokens', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['token' => 'tok-1'], json_decode((string) $this->lastRequest()->getBody(), true));

        $contacts->removePushToken('user@example.com', 'tok-1');
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame(['token' => 'tok-1'], json_decode((string) $this->lastRequest()->getBody(), true));
    }

    /**
     * Regression: request() declares `: array`, and json_decode() returns null
     * for an empty or non-JSON body. Before 0.5.1 that was a TypeError.
     *
     * @dataProvider unparseableBodies
     */
    public function testUnparseableSuccessBodyYieldsAnEmptyArray(string $body): void
    {
        $contacts = $this->contacts([new Response(200, [], $body)]);

        $this->assertSame([], $contacts->get('user@example.com'));
    }

    /** @return array<string, array{0: string}> */
    public static function unparseableBodies(): array
    {
        return [
            'empty body' => [''],
            'html error page' => ['<html>ok</html>'],
            'truncated json' => ['{"success":tr'],
            'json scalar' => ['"ok"'],
        ];
    }

    public function testMissingContactRaisesApiExceptionWithStatus(): void
    {
        $failure = new RequestException(
            'Not Found',
            new Request('GET', 'v1/contacts/nobody@example.com'),
            new Response(404, [], '{"error":"Contact not found"}'),
        );

        try {
            $this->contacts([$failure])->get('nobody@example.com');
            $this->fail('Expected an ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertStringContainsString('Contact not found', $e->getMessage());
        }
    }

    public function testConnectionFailureRaisesTransportException(): void
    {
        $failure = new ConnectException('timed out', new Request('GET', 'v1/contacts/x'));

        $this->expectException(TransportException::class);
        $this->contacts([$failure])->get('x@example.com');
    }

    public function testContactsInstanceIsReused(): void
    {
        $sendivent = new Sendivent('test_key');

        $this->assertSame($sendivent->contacts(), $sendivent->contacts());
    }
}
