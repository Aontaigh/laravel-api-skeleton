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
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and tighten the API limit for the test run.
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
}
