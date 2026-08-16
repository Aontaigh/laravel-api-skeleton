<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the baseline security headers on API and HTML responses.
 */
#[CoversClass(SecurityHeaders::class)]
final class SecurityHeadersTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Attach the baseline headers to a JSON API response.
     */
    #[Test]
    public function it_attaches_baseline_headers_to_an_api_response(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'x',
        ]);

        // Assert

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
        $response->assertHeader('Content-Security-Policy');
    }

    /**
     * Permit the Scalar CDN and inline bootstrap the API-docs page depends on.
     */
    #[Test]
    public function it_permits_the_scalar_cdn_in_the_csp(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->get('/api/docs');

        // Assert

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    /**
     * Omit HSTS on plain-http local requests.
     */
    #[Test]
    public function it_omits_hsts_on_plain_http_local_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->get('/api/docs');

        // Assert

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }
}
