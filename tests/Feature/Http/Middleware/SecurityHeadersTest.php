<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for the baseline security headers on API and HTML responses.
 */
#[CoversClass(SecurityHeaders::class)]
final class SecurityHeadersTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Tear down Vite facade mocks between tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

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
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'x',
        ]);

        // Assert

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
        $response->assertHeader('Content-Security-Policy');
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    /**
     * Enforce the CSP in tests rather than emitting a report-only header.
     */
    #[Test]
    public function it_enforces_the_csp_in_the_testing_environment(): void
    {
        // Assert

        $this->assertTrue(config()->boolean('security.csp_enforce'));

        // Act

        /** @var TestResponse<Response> $response */
        $response = $this->get('/api/docs');

        // Assert

        $response->assertHeader('Content-Security-Policy');
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    /**
     * Permit the Scalar CDN and inline bootstrap only on the API-docs page.
     */
    #[Test]
    public function it_applies_a_relaxed_csp_to_the_api_docs_page(): void
    {
        // Act

        /** @var TestResponse<Response> $response */
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

        /** @var TestResponse<Response> $response */
        $response = $this->get('/api/docs');

        // Assert

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    /**
     * Omit the CSP while Vite hot-reload is active in local development.
     */
    #[Test]
    public function it_omits_the_csp_when_vite_is_running_hot_in_local(): void
    {
        // Arrange

        $this->app->detectEnvironment(static fn (): string => 'local');

        Vite::shouldReceive('isRunningHot')
            ->once()
            ->andReturn(true);

        // Act

        /** @var TestResponse<Response> $response */
        $response = $this->get('/api/docs');

        // Assert

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * Keep the CSP when Vite is not serving hot assets in local development.
     */
    #[Test]
    public function it_keeps_the_csp_when_vite_is_not_running_hot_in_local(): void
    {
        // Arrange

        $this->app->detectEnvironment(static fn (): string => 'local');

        Vite::shouldReceive('isRunningHot')
            ->once()
            ->andReturn(false);

        // Act

        /** @var TestResponse<Response> $response */
        $response = $this->get('/api/docs');

        // Assert

        $response->assertHeader('Content-Security-Policy');
    }
}
