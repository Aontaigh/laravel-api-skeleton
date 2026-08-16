<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Support\ApiResponse;
use Database\Seeders\ApiClientsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for client-credentials token exchange rate limiting.
 */
#[CoversClass(ApiResponse::class)]
final class ApiClientAuthRateLimitingTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Tighten the client-auth limit for the test run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApiClientsSeeder::class);

        RateLimiter::for('api-client-auth', static function (Request $request) {
            $clientId = $request->string('client_id', '')->toString();

            return Limit::perMinute(2)->by($request->ip().'|'.$clientId);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope when client-credentials exchange is rate limited.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_client_exchange_is_rate_limited(): void
    {
        // Arrange

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => ApiClientsSeeder::DEMO_CLIENT_ID,
            'client_secret' => 'wrong-secret-value',
        ];

        // Act

        $this->postJson('/api/oauth/token', $payload)->assertUnprocessable();
        $this->postJson('/api/oauth/token', $payload)->assertUnprocessable();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', $payload);

        // Assert

        $response->assertStatus(429);
        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }
}
