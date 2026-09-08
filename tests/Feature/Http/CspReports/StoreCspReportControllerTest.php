<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CspReports;

use App\Actions\CspReports\LogCspReportAction;
use App\Http\Controllers\CspReports\StoreCspReportController;
use App\Http\Requests\CspReports\StoreCspReportRequest;
use App\Services\CspReports\CspReportPayloadParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests for `POST /api/csp-reports`.
 */
#[CoversClass(StoreCspReportController::class)]
#[CoversClass(StoreCspReportRequest::class)]
#[CoversClass(CspReportPayloadParser::class)]
#[CoversClass(LogCspReportAction::class)]
final class StoreCspReportControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AssertsApiEnvelope;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Reporting Shape Tests
     * ---------------------
     */

    /**
     * Accept a legacy `report-uri` body without requiring authentication.
     */
    #[Test]
    public function it_accepts_a_legacy_csp_report_body_without_authentication(): void
    {
        // Arrange

        $body = json_encode([
            'csp-report' => [
                'document-uri' => 'https://example.com/page',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'https://evil.example/x.js',
                'source-file' => 'https://example.com/app.js',
                'line-number' => 42,
                'disposition' => 'enforce',
                'original-policy' => "default-src 'self'",
            ],
        ]);
        self::assertIsString($body);

        // Act

        $response = $this->call('POST', '/api/csp-reports', server: [
            'CONTENT_TYPE' => 'application/csp-report',
        ], content: $body);

        // Assert

        $response->assertNoContent();
    }

    /**
     * Accept a modern `report-to` array body (Reporting API shape).
     */
    #[Test]
    public function it_accepts_a_reports_plus_json_array_body(): void
    {
        // Arrange

        $body = json_encode([
            [
                'type' => 'csp-violation',
                'url' => 'https://example.com/page',
                'body' => [
                    'documentURL' => 'https://example.com/page',
                    'effectiveDirective' => 'script-src',
                    'blockedURL' => 'https://evil.example/x.js',
                    'disposition' => 'enforce',
                ],
            ],
        ]);
        self::assertIsString($body);

        // Act

        $response = $this->call('POST', '/api/csp-reports', server: [
            'CONTENT_TYPE' => 'application/reports+json',
        ], content: $body);

        // Assert

        $response->assertNoContent();
    }

    /**
     * Respond gracefully - never 500 - to a malformed JSON body.
     */
    #[Test]
    public function it_gracefully_handles_a_malformed_json_body(): void
    {
        // Act

        $response = $this->call('POST', '/api/csp-reports', server: [
            'CONTENT_TYPE' => 'application/csp-report',
        ], content: '{not valid json');

        // Assert

        $response->assertNoContent();
    }

    /**
     * Acknowledge an empty body the same way - browsers never read it.
     */
    #[Test]
    public function it_gracefully_handles_an_empty_body(): void
    {
        // Act

        $response = $this->call('POST', '/api/csp-reports', server: [
            'CONTENT_TYPE' => 'application/csp-report',
        ], content: '');

        // Assert

        $response->assertNoContent();
    }

    /**
     * Reject an oversized body before it is ever decoded.
     *
     * The cap sits at 16 KiB; asserting `Log::channel()` is never reached
     * proves the guard runs before `json_decode`, not just before persisting.
     */
    #[Test]
    public function it_rejects_an_oversized_body_without_decoding(): void
    {
        // Arrange

        $oversizedBody = json_encode(['csp-report' => ['document-uri' => str_repeat('a', 20 * 1024)]]);
        self::assertIsString($oversizedBody);

        /*
         * The global request-ID middleware attaches log context on every
         * request, so the context call must be allowed while the channel
         * itself stays forbidden.
         */
        Log::shouldReceive('withContext')->zeroOrMoreTimes()->andReturnSelf();
        Log::shouldReceive('channel')->never();

        // Act

        $response = $this->call('POST', '/api/csp-reports', server: [
            'CONTENT_TYPE' => 'application/csp-report',
        ], content: $oversizedBody);

        // Assert

        $response->assertStatus(413);
        $response->assertNoContent(413);
    }

    /*
     * Envelope and Security Tests
     * ---------------------------
     */

    /**
     * Never wrap the success response in the `ApiResponse` JSON envelope.
     */
    #[Test]
    public function it_does_not_wrap_the_success_response_in_the_api_envelope(): void
    {
        // Act

        $response = $this->call('POST', '/api/csp-reports', server: [
            'CONTENT_TYPE' => 'application/csp-report',
        ], content: '{"csp-report":{"document-uri":"https://example.com/page"}}');

        // Assert

        $response->assertNoContent();
        self::assertSame('', $response->getContent());
    }

    /**
     * Succeed without any credentials even when the request looks first-party.
     *
     * A browser's built-in CSP violation reporter never attaches an auth
     * token. Sending a stateful-domain `Origin` header here proves the route
     * is genuinely public rather than accidentally authenticated.
     */
    #[Test]
    public function it_succeeds_without_credentials_from_a_stateful_origin(): void
    {
        // Act

        $response = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('POST', '/api/csp-reports', server: [
                'CONTENT_TYPE' => 'application/csp-report',
            ], content: '{"csp-report":{"document-uri":"https://example.com/page"}}');

        // Assert

        $response->assertNoContent();
    }

    /*
     * Rate Limiting Tests
     * -------------------
     */

    /**
     * Return the standard envelope when the `csp-reports` limiter is exceeded.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_csp_reports_are_rate_limited(): void
    {
        // Arrange

        RateLimiter::for('csp-reports', static function (Request $request): array {
            return [Limit::perMinute(2)->by((string) $request->ip())];
        });

        $body = '{"csp-report":{"document-uri":"https://example.com/page"}}';

        // Act

        $this->call('POST', '/api/csp-reports', server: ['CONTENT_TYPE' => 'application/csp-report'], content: $body)->assertNoContent();
        $this->call('POST', '/api/csp-reports', server: ['CONTENT_TYPE' => 'application/csp-report'], content: $body)->assertNoContent();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->call('POST', '/api/csp-reports', server: ['CONTENT_TYPE' => 'application/csp-report'], content: $body);

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }
}
