<?php

namespace Sendivent\Tests;

use PHPUnit\Framework\TestCase;
use Sendivent\SendResponse;

class SendResponseTest extends TestCase
{
    public function testParsesTheRealApiPayload(): void
    {
        $response = SendResponse::from([
            'id' => 'dd736a93-0031-4969-94c1-66e455ff1bbd',
            'event' => 'receipt',
            'status' => 'accepted',
        ]);

        $this->assertSame('dd736a93-0031-4969-94c1-66e455ff1bbd', $response->id);
        $this->assertSame('receipt', $response->event);
        $this->assertSame('accepted', $response->status);
        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->hasError());
    }

    /**
     * Regression: the API has never sent a `success` key. Any field the SDK
     * reads must tolerate its absence rather than blowing up the caller.
     *
     * @dataProvider degenerateBodies
     * @param array<string, mixed>|null $body
     */
    public function testNeverThrowsOnAnIncompleteBody(?array $body): void
    {
        $response = SendResponse::from($body);

        $this->assertSame('', $response->id);
        $this->assertSame('', $response->event);
        $this->assertSame('', $response->status);
        $this->assertFalse($response->isSuccess());
    }

    /** @return array<string, array{0: array<string, mixed>|null}> */
    public static function degenerateBodies(): array
    {
        return [
            'null (unparseable body)' => [null],
            'empty object' => [[]],
            'unrelated keys' => [['foo' => 'bar']],
        ];
    }

    public function testSurfacesAnErrorField(): void
    {
        $response = SendResponse::from(['status' => 'rejected', 'error' => 'No sender configured']);

        $this->assertTrue($response->hasError());
        $this->assertFalse($response->isSuccess());
        $this->assertSame('No sender configured', $response->error);
    }

    public function testToArrayOmitsNulls(): void
    {
        $array = SendResponse::from(['id' => 'x', 'event' => 'e', 'status' => 'accepted'])->toArray();

        $this->assertSame(['id' => 'x', 'event' => 'e', 'status' => 'accepted'], $array);
        $this->assertArrayNotHasKey('error', $array);
    }
}
