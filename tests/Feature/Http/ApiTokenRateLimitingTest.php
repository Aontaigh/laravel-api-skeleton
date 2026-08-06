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
 * Feature tests for Personal Access Token creation rate limiting.
 */
#[CoversClass(ApiResponse::class)]
final class ApiTokenRateLimitingTest extends TestCase
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
     * Seed permissions and tighten the token-creation limit for the test run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        RateLimiter::for('api-tokens', static function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(2)->by($user !== null ? (string) $user->id : $request->ip());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope when Token creation is rate limited.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_token_creation_is_rate_limited(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        $this->actingAs($viewer)->postJson('/api/tokens', ['name' => 'first'])->assertCreated();
        $this->actingAs($viewer)->postJson('/api/tokens', ['name' => 'second'])->assertCreated();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', ['name' => 'third']);

        // Assert

        $response->assertStatus(429);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 429);
        $response->assertJsonPath('message', 'Too Many Requests');
    }

    /**
     * Rate-limit admin-issued Tokens separately from self-service Tokens.
     */
    #[Test]
    public function it_rate_limits_admin_issued_tokens_separately_from_self_service_tokens(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$target->id}/tokens", ['name' => 'one'])->assertCreated();
        $this->actingAs($admin)->postJson("/api/users/{$target->id}/tokens", ['name' => 'two'])->assertCreated();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$target->id}/tokens", ['name' => 'three']);

        // Assert

        $response->assertStatus(429);
    }
}
