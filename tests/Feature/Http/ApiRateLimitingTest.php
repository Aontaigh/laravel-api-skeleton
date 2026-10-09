<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Support\ApiResponse;
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
 * Feature tests for API rate limiting.
 */
#[CoversClass(ApiResponse::class)]
final class ApiRateLimitingTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and tighten the API limit for the test run.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        RateLimiter::for('api', static function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(3)->by($user !== null ? (string) $user->id : $request->ip());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope when the API rate limit is exceeded.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_the_api_rate_limit_is_exceeded(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)->getJson('/api/users')->assertOk();
        }

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users');

        // Assert

        $response->assertStatus(429);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 429);
        $response->assertJsonPath('message', 'Too Many Requests');
        $response->assertJsonPath('data', null);
    }

    /**
     * Send the framework's advisory headers on the throttled response.
     *
     * The envelope replaces the response the framework would have decorated, so
     * `Retry-After`
     * ([RFC 9110 section 10.2.3](https://www.rfc-editor.org/rfc/rfc9110#section-10.2.3))
     * and the `X-RateLimit-*` pair must be copied across, or a client cannot
     * know how long to wait and retries immediately.
     */
    #[Test]
    public function it_sends_the_advisory_headers_when_throttled(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)->getJson('/api/users')->assertOk();
        }

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users');

        // Assert

        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit', '3');
        $response->assertHeader('X-RateLimit-Remaining', '0');
        $response->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }
}
