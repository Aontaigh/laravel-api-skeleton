<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\MfaMethod;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for two-factor send and verify rate limiting.
 */
#[CoversClass(ApiResponse::class)]
#[CoversClass(AppServiceProvider::class)]
final class ApiTwoFactorAuthRateLimitingTest extends TestCase
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
     * Seed permissions and tighten the two-factor limiters for the test run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Notification::fake();

        RateLimiter::for('api-auth-two-factor-send', static function (Request $request) {
            return Limit::perMinute(2)->by((string) $request->ip());
        });

        RateLimiter::for('api-auth-two-factor-verify', static function (Request $request) {
            return Limit::perMinute(2)->by((string) $request->ip());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Rate-limit resend requests without consuming the verify-code budget.
     */
    #[Test]
    public function it_rate_limits_send_and_verify_independently(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'mfa@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $twoFactorToken = $this->beginStatelessTwoFactorChallenge($user);

        $sendPayload = [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ];

        // Act

        $this->postJson('/api/two-factor/send', $sendPayload)->assertOk();
        $this->postJson('/api/two-factor/send', $sendPayload)->assertOk();

        /** @var TestResponse<JsonResponse> $rateLimitedSend */
        $rateLimitedSend = $this->postJson('/api/two-factor/send', $sendPayload);

        Cache::put('two-factor:'.$user->id, [
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);

        /** @var TestResponse<JsonResponse> $verifyResponse */
        $verifyResponse = $this->postJson('/api/two-factor/verify', [
            'code' => '123456',
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $this->assertApiErrorEnvelope($rateLimitedSend, 429, 'Too Many Requests');
        $verifyResponse->assertOk();
    }

    /**
     * Rate-limit code guesses without consuming the resend budget.
     */
    #[Test]
    public function it_rate_limits_verify_without_consuming_the_send_budget(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'verify-limit@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $twoFactorToken = $this->beginStatelessTwoFactorChallenge($user);

        Cache::put('two-factor:'.$user->id, [
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);

        $verifyPayload = [
            'code' => '000000',
            'two_factor_token' => $twoFactorToken,
        ];

        // Act

        $this->postJson('/api/two-factor/verify', $verifyPayload)->assertUnprocessable();
        $this->postJson('/api/two-factor/verify', $verifyPayload)->assertUnprocessable();

        /** @var TestResponse<JsonResponse> $rateLimitedVerify */
        $rateLimitedVerify = $this->postJson('/api/two-factor/verify', $verifyPayload);

        /** @var TestResponse<JsonResponse> $sendResponse */
        $sendResponse = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $this->assertApiErrorEnvelope($rateLimitedVerify, 429, 'Too Many Requests');
        $sendResponse->assertOk();
    }

    /**
     * Rate-limit status polling without consuming the send or verify budgets.
     */
    #[Test]
    public function it_rate_limits_status_polling_without_consuming_the_send_or_verify_budgets(): void
    {
        // Arrange

        RateLimiter::for('api-auth-two-factor-status', static function (Request $request) {
            return Limit::perMinute(2)->by((string) $request->ip());
        });

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'status-limit@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $twoFactorToken = $this->beginStatelessTwoFactorChallenge($user);

        // Act

        $this->getJson('/api/two-factor/status?two_factor_token='.$twoFactorToken)->assertOk();
        $this->getJson('/api/two-factor/status?two_factor_token='.$twoFactorToken)->assertOk();

        /** @var TestResponse<JsonResponse> $rateLimitedStatus */
        $rateLimitedStatus = $this->getJson('/api/two-factor/status?two_factor_token='.$twoFactorToken);

        /** @var TestResponse<JsonResponse> $sendResponse */
        $sendResponse = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $this->assertApiErrorEnvelope($rateLimitedStatus, 429, 'Too Many Requests');
        $sendResponse->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Start a pending challenge and return its opaque token without a session cookie.
     */
    private function beginStatelessTwoFactorChallenge(User $user): string
    {
        /** @var TestResponse<JsonResponse> $login */
        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $login->assertOk();

        $twoFactorToken = $login->json('data.two_factor_token');
        $this->assertIsString($twoFactorToken);

        $this->flushSession();

        return $twoFactorToken;
    }
}
