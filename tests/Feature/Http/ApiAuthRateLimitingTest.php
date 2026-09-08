<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Providers\AppServiceProvider;
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
 * Feature tests for login and registration rate limiting.
 */
#[CoversClass(ApiResponse::class)]
#[CoversClass(AppServiceProvider::class)]
final class ApiAuthRateLimitingTest extends TestCase
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
     * Tighten the auth limits for the test run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $tighten = static function (Request $request) {
            $email = $request->string('email', '')->lower()->toString();

            return Limit::perMinute(2)->by($request->ip().'|'.$email);
        };

        RateLimiter::for('api-auth-login', $tighten);
        RateLimiter::for('api-auth-register', $tighten);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope when login is rate limited.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_login_is_rate_limited(): void
    {
        // Arrange

        $payload = [
            'email' => 'attacker@example.com',
            'password' => 'WrongPass1',
        ];

        // Act

        $this->postJson('/api/auth/login', $payload)->assertUnprocessable();
        $this->postJson('/api/auth/login', $payload)->assertUnprocessable();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', $payload);

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }

    /**
     * Return the standard envelope when registration is rate limited.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_registration_is_rate_limited(): void
    {
        // Arrange

        $payload = [
            'name' => 'Alice',
            'email' => 'attacker@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ];

        // Act

        $this->postJson('/api/auth/register', $payload)->assertCreated();
        $this->postJson('/api/auth/register', $payload)->assertUnprocessable();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/register', $payload);

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }

    /**
     * Key the composite limiter on credential+IP so one account's abuse does
     * not throttle a different account from the same address.
     */
    #[Test]
    public function it_does_not_throttle_a_different_email_from_the_same_ip(): void
    {
        // Arrange

        $firstPayload = [
            'email' => 'first@example.com',
            'password' => 'WrongPass1',
        ];

        // Act

        /*
         * Exhaust the composite bucket for the first email, then show a
         * different email from the same IP still reaches validation.
         */

        $this->postJson('/api/auth/login', $firstPayload)->assertUnprocessable();
        $this->postJson('/api/auth/login', $firstPayload)->assertUnprocessable();
        $this->postJson('/api/auth/login', $firstPayload)->assertStatus(429);

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'second@example.com',
            'password' => 'WrongPass1',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Apply the broad per-IP ceiling from the provider when the app is not local.
     */
    #[Test]
    public function it_applies_the_per_ip_ceiling_outside_local(): void
    {
        // Arrange

        /*
         * Rebuild the production limiter shape with a tiny ceiling so the
         * test finishes quickly: a generous composite bucket plus a 3-per-IP
         * ceiling that the fourth distinct email must trip.
         */

        RateLimiter::for('api-auth-login', function (Request $request): array {
            $email = $request->string('email', '')->lower()->toString();

            return [
                Limit::perMinute(100)->by($request->ip().'|'.$email),
                Limit::perMinute(3)->by((string) $request->ip()),
            ];
        });

        // Act

        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $this->postJson('/api/auth/login', [
                'email' => $email,
                'password' => 'WrongPass1',
            ])->assertUnprocessable();
        }

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'd@example.com',
            'password' => 'WrongPass1',
        ]);

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }

    /**
     * Isolate login and registration budgets: exhausting registration must not
     * throttle login from the same address.
     */
    #[Test]
    public function it_isolates_login_and_registration_budgets(): void
    {
        // Arrange

        RateLimiter::for('api-auth-register', static function (Request $request) {
            $email = $request->string('email', '')->lower()->toString();

            return Limit::perMinute(1)->by($request->ip().'|'.$email);
        });

        $payload = [
            'name' => 'Alice',
            'email' => 'budget@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ];

        // Act

        $this->postJson('/api/auth/register', $payload)->assertCreated();
        $this->postJson('/api/auth/register', $payload)->assertStatus(429);

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'budget@example.com',
            'password' => 'WrongPass1',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Return the standard envelope when password change is rate limited.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_password_change_is_rate_limited(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        RateLimiter::for('auth-password-change', static function (Request $request) {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(2)->by((is_scalar($identifier) ? (string) $identifier : (string) $request->ip()).'|'.$request->ip());
        });

        $payload = [
            'current_password' => 'WrongPass1',
            'password' => 'NewSecretPass13',
            'password_confirmation' => 'NewSecretPass13',
        ];

        // Act

        $this->actingAs($user)->patchJson('/api/me/password', $payload)->assertUnprocessable();
        $this->actingAs($user)->patchJson('/api/me/password', $payload)->assertUnprocessable();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', $payload);

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }

    /**
     * Return the standard envelope when session revokes are rate limited.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_session_revokes_are_rate_limited(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        RateLimiter::for('auth-sessions-revoke', static function (Request $request) {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(2)->by((is_scalar($identifier) ? (string) $identifier : (string) $request->ip()).'|'.$request->ip());
        });

        // Act

        $this->actingAs($user)->deleteJson('/api/sessions/999999')->assertNotFound();
        $this->actingAs($user)->deleteJson('/api/sessions/999999')->assertNotFound();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->deleteJson('/api/sessions/999999');

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');
    }
}
