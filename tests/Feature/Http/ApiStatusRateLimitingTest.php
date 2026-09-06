<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Queries\SystemHealth\SystemHealthHistoryQuery;
use App\Services\SystemHealth\SystemHealthCheckRegistry;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the public System Status rate limiter.
 */
#[CoversClass(ApiResponse::class)]
#[CoversClass(SystemHealthHistoryQuery::class)]
#[CoversClass(SystemHealthCheckRegistry::class)]
final class ApiStatusRateLimitingTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope when the status limiter is exhausted.
     *
     * The limiter reads `api.status_rate_limit_per_minute` at request time,
     * so tightening config here exercises the real registration in
     * AppServiceProvider rather than a test-only re-registration.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_the_status_limit_is_exceeded(): void
    {
        // Arrange

        config(['api.status_rate_limit_per_minute' => 2]);

        // Act

        $this->getJson('/api/status')->assertOk();
        $this->getJson('/api/status')->assertOk();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/status');

        // Assert

        $response->assertStatus(429);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 429);
        $response->assertJsonPath('message', 'Too Many Requests');
        $response->assertJsonPath('data', null);
    }
}
