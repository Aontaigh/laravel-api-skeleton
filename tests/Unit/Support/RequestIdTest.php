<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RequestId;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for request correlation ID resolution and validation.
 */
#[CoversClass(RequestId::class)]
final class RequestIdTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Honour a well-formed inbound request ID.
     */
    #[Test]
    public function it_honours_a_well_formed_inbound_request_id(): void
    {
        // Arrange

        $request = Request::create('/', server: ['HTTP_X_REQUEST_ID' => 'req-abc_123.~']);

        // Act

        $resolved = RequestId::current($request);

        // Assert

        $this->assertSame('req-abc_123.~', $resolved);
    }

    /**
     * Fall back to the trace ID of a well-formed `traceparent` header.
     */
    #[Test]
    public function it_falls_back_to_the_traceparent_trace_id(): void
    {
        // Arrange

        $traceId = '0af7651916cd43dd8448eb211c80319c';
        $request = Request::create('/', server: ['HTTP_TRACEPARENT' => "00-{$traceId}-b7ad6b7169203331-01"]);

        // Act

        $resolved = RequestId::current($request);

        // Assert

        $this->assertSame($traceId, $resolved);
    }

    /**
     * Prefer the request ID over `traceparent` when both are present.
     */
    #[Test]
    public function it_prefers_the_request_id_over_traceparent(): void
    {
        // Arrange

        $request = Request::create('/', server: [
            'HTTP_X_REQUEST_ID' => 'caller-id',
            'HTTP_TRACEPARENT' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
        ]);

        // Act

        $resolved = RequestId::current($request);

        // Assert

        $this->assertSame('caller-id', $resolved);
    }

    /**
     * Generate a fresh UUID when no usable ID is sent.
     *
     * @param array<string, string> $server the server parameters carrying the headers
     */
    #[Test]
    #[DataProvider('unusableIdProvider')]
    public function it_generates_a_fresh_uuid_without_a_usable_id(array $server): void
    {
        // Arrange

        $request = Request::create('/', server: $server);

        // Act

        $resolved = RequestId::current($request);

        // Assert

        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
            $resolved,
        );
    }

    /**
     * Judge ID safety for headers, logs, and columns.
     *
     * @param string $value    the candidate ID
     * @param bool   $expected whether the value may be honoured as-is
     */
    #[Test]
    #[DataProvider('idValidityProvider')]
    public function it_judges_id_safety(string $value, bool $expected): void
    {
        // Act + Assert

        $this->assertSame($expected, RequestId::isValid($value));
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Header sets carrying no usable correlation ID.
     *
     * @return array<string, array{0: array<string, string>}> case name mapped to [server params]
     */
    public static function unusableIdProvider(): array
    {
        return [
            'no headers' => [[]],
            'blank ID' => [['HTTP_X_REQUEST_ID' => '']],
            'oversized ID' => [['HTTP_X_REQUEST_ID' => str_repeat('a', 129)]],
            'newline injection' => [['HTTP_X_REQUEST_ID' => "abc\ndef"]],
            'non-hex traceparent' => [['HTTP_TRACEPARENT' => '00-nothex-b7ad6b7169203331-01']],
            'all-zero trace ID' => [['HTTP_TRACEPARENT' => '00-00000000000000000000000000000000-b7ad6b7169203331-01']],
        ];
    }

    /**
     * Candidate IDs mapped to whether they may be honoured as-is.
     *
     * @return array<string, array{0: string, 1: bool}> case name mapped to [value, expected]
     */
    public static function idValidityProvider(): array
    {
        return [
            'uuid' => ['123e4567-e89b-12d3-a456-426614174000', true],
            'stripe style' => ['req_abc123', true],
            'trace ID' => ['0af7651916cd43dd8448eb211c80319c', true],
            'empty' => ['', false],
            'spaces' => ['has space', false],
            'newline' => ["a\nb", false],
            'quotes' => ['a"b', false],
        ];
    }
}
