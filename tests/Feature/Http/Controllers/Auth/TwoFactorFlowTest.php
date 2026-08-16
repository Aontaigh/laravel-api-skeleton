<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\FinaliseAuthenticatedSessionAction;
use App\Actions\Auth\IssueTwoFactorChallengeAction;
use App\Actions\Auth\VerifyTwoFactorCodeAction;
use App\DataTransferObjects\Auth\FinaliseAuthenticatedSessionData;
use App\Enums\AuthAuditEvent;
use App\Enums\MfaMethod;
use App\Exceptions\Auth\TwoFactorChallengeException;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SendTwoFactorController;
use App\Http\Controllers\Auth\VerifyTwoFactorController;
use App\Http\Requests\Auth\SendTwoFactorRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Models\User;
use App\Notifications\Auth\TwoFactorCodeNotification;
use App\Support\Auth\PendingTwoFactor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the two-factor authentication flow.
 */
#[CoversClass(LoginController::class)]
#[CoversClass(RegisterController::class)]
#[CoversClass(SendTwoFactorController::class)]
#[CoversClass(VerifyTwoFactorController::class)]
#[CoversClass(FinaliseAuthenticatedSessionAction::class)]
#[CoversClass(FinaliseAuthenticatedSessionData::class)]
#[CoversClass(SendTwoFactorRequest::class)]
#[CoversClass(VerifyTwoFactorRequest::class)]
#[CoversClass(IssueTwoFactorChallengeAction::class)]
#[CoversClass(VerifyTwoFactorCodeAction::class)]
#[CoversClass(PendingTwoFactor::class)]
#[CoversClass(TwoFactorChallengeException::class)]
#[CoversClass(TwoFactorCodeNotification::class)]
final class TwoFactorFlowTest extends TestCase
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
     * Seed roles and permissions.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Lift the auth rate limit so lockout tests aren't throttled mid-flow.
     */
    private function liftAuthRateLimit(): void
    {
        config(['api.auth_rate_limit_per_minute' => 100]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Login → Challenge Tests
     * -----------------------
     */

    /**
     * A user with MFA enabled is challenged after valid credentials.
     */
    #[Test]
    public function it_returns_two_factor_required_when_mfa_is_enabled(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'mfa@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/login', [
            'email' => 'mfa@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonPath('message', 'Two-Factor Required');
        $response->assertJsonStructure(['data' => ['two_factor_token']]);
        $response->assertJsonMissingPath('data.token');
        $response->assertJsonMissingPath('data.plain_text_token');
    }

    /**
     * A user without MFA gets a token immediately (no challenge).
     */
    #[Test]
    public function it_issues_a_token_immediately_when_mfa_is_disabled(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'nomfa@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => null,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/login', [
            'email' => 'nomfa@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged In Successfully');
        $response->assertJsonStructure(['data' => ['user', 'token', 'plain_text_token']]);
        $response->assertJsonMissingPath('data.two_factor_required');
    }

    /*
     * Send Code Tests
     * ---------------
     */

    /**
     * Dispatch a six-digit code after a pending challenge.
     */
    #[Test]
    public function it_sends_a_six_digit_code_via_email(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'send@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        // Act

        $this->postJson('/api/login', [
            'email' => 'send@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonPath('message', 'Two-Factor Code Sent');

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $this->assertArrayHasKey('code_hash', $challenge);
        $this->assertArrayHasKey('attempts', $challenge);
        $this->assertSame(0, $challenge['attempts']);
    }

    /**
     * Reject send without a pending challenge.
     */
    #[Test]
    public function it_rejects_send_without_a_pending_challenge(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Your Sign-In Session Has Expired');
    }

    /**
     * Reject send for a suspended user.
     */
    #[Test]
    public function it_rejects_send_for_a_suspended_user(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        // Open the challenge, then suspend

        $this->postJson('/api/login', [
            'email' => 'suspended@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $user->forceFill(['suspended_at' => now()])->save();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
        ]);

        // Assert

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account Suspended');
    }

    /**
     * Reject a channel that does not match the user's enrolled MFA method.
     */
    #[Test]
    public function it_rejects_send_when_the_channel_does_not_match_the_enrolled_method(): void
    {
        // Arrange

        User::factory()->create([
            'email' => 'channel@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'channel@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Act — only email is wired; sms is not a valid enum value today,
        // so we simulate a future channel by tampering with mfa_method after login.

        /** @var User $user */
        $user = User::query()->where('email', 'channel@example.com')->firstOrFail();
        $user->forceFill(['mfa_method' => null])->save();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Invalid Channel');
    }

    /*
     * Verify Code Tests
     * -----------------
     */

    /**
     * Verify a correct code and receive a token.
     */
    #[Test]
    public function it_verifies_a_correct_code_and_issues_a_token(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'verify@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        // Login + send

        $this->postJson('/api/login', [
            'email' => 'verify@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Extract the code from the cache to verify against

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $codeHash = $challenge['code_hash'];

        // We can't reverse the hash, so generate a known code and re-hash it
        $code = '123456';
        Cache::put('two-factor:'.$user->id, [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => $code,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged In Successfully');
        $response->assertJsonStructure(['data' => ['user', 'token', 'plain_text_token']]);
        $response->assertJsonPath('data.user.email', 'verify@example.com');

        // Challenge is consumed
        $this->assertNull(Cache::get('two-factor:'.$user->id));
    }

    /**
     * Reject a wrong code.
     */
    #[Test]
    public function it_rejects_a_wrong_code(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => '000000',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Invalid or Expired Code');
    }

    /**
     * Reject a code after too many attempts.
     */
    #[Test]
    public function it_locks_out_after_max_attempts(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'lockout@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Act

        $this->liftAuthRateLimit();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/two-factor/verify', ['code' => '000000']);
        }

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => '000000',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Invalid or Expired Code');

        // Challenge is torn down
        $this->assertNull(Cache::get('two-factor:'.$user->id));
    }

    /**
     * Reject a code without a pending challenge.
     */
    #[Test]
    public function it_rejects_verify_without_a_pending_challenge(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => '123456',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Invalid or Expired Code');
    }

    /**
     * Reject a suspended user mid-flight.
     */
    #[Test]
    public function it_rejects_verify_for_a_suspended_user(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'midsuspend@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'midsuspend@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Suspend mid-flight

        $user->forceFill(['suspended_at' => now()])->save();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => '123456',
        ]);

        // Assert

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account Suspended');
    }

    /**
     * Resend preserves the attempt counter from the previous challenge.
     */
    #[Test]
    public function it_preserves_attempt_count_on_resend(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'resend@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'resend@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Send the first code

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Burn 3 attempts

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/two-factor/verify', ['code' => '000000']);
        }

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $this->assertSame(3, $challenge['attempts']);

        // Act

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Assert

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $this->assertSame(3, $challenge['attempts']);
    }

    /**
     * Expired challenge resets the attempt counter on resend.
     */
    #[Test]
    public function it_resets_attempt_count_when_the_challenge_expires(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'expired-resend@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'expired-resend@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Send the first code

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Burn 3 attempts

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/two-factor/verify', ['code' => '000000']);
        }

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $this->assertSame(3, $challenge['attempts']);

        // Simulate cache expiry by removing the challenge

        Cache::forget('two-factor:'.$user->id);

        // Act

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Assert — a fresh challenge starts with zero attempts

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $this->assertSame(0, $challenge['attempts']);
    }

    /**
     * Start a fresh attempt budget when credentials are verified again.
     */
    #[Test]
    public function it_resets_attempt_count_after_a_fresh_login(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'freshlogin@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'freshlogin@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/two-factor/verify', ['code' => '000000']);
        }

        // Act

        $this->postJson('/api/login', [
            'email' => 'freshlogin@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Assert

        $challenge = Cache::get('two-factor:'.$user->id);
        $this->assertIsArray($challenge);
        $this->assertSame(0, $challenge['attempts']);
    }

    /**
     * Invalidate a superseded opaque token when credentials are verified again.
     */
    #[Test]
    public function it_invalidates_a_previous_opaque_token_after_a_fresh_login(): void
    {
        // Arrange

        Notification::fake();

        User::factory()->create([
            'email' => 'supersede@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        /** @var TestResponse<JsonResponse> $firstLogin */
        $firstLogin = $this->postJson('/api/login', [
            'email' => 'supersede@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $staleToken = $firstLogin->json('data.two_factor_token');
        $this->assertIsString($staleToken);

        $this->flushSession();

        $this->postJson('/api/login', [
            'email' => 'supersede@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->flushSession();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $staleToken,
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Your Sign-In Session Has Expired');
    }

    /**
     * Complete send and verify using only the opaque token (no session cookie).
     */
    #[Test]
    public function it_supports_stateless_send_and_verify_with_the_opaque_token(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'stateless@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        /** @var TestResponse<JsonResponse> $login */
        $login = $this->postJson('/api/login', [
            'email' => 'stateless@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $twoFactorToken = $login->json('data.two_factor_token');
        $this->assertIsString($twoFactorToken);

        $this->flushSession();

        $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ]);

        $code = '654321';
        Cache::put('two-factor:'.$user->id, [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => $code,
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged In Successfully');
        $response->assertJsonStructure(['data' => ['user', 'token', 'plain_text_token']]);
    }

    /**
     * Remember-me flows through the pending challenge into the issued token.
     */
    #[Test]
    public function it_applies_remember_me_after_two_factor_verify(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'remember@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        /** @var TestResponse<JsonResponse> $login */
        $login = $this->postJson('/api/login', [
            'email' => 'remember@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'remember' => true,
        ]);

        $twoFactorToken = $login->json('data.two_factor_token');
        $this->assertIsString($twoFactorToken);

        $this->flushSession();

        $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ]);

        $code = '654321';
        Cache::put('two-factor:'.$user->id, [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => $code,
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged In Successfully');

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'API Session',
        ]);

        $token = \Laravel\Sanctum\PersonalAccessToken::query()->latest('id')->first();
        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->gt(now()->addDays(30)));
    }

    /**
     * Reject an expired pending challenge.
     */
    #[Test]
    public function it_rejects_an_expired_pending_challenge(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        /** @var TestResponse<JsonResponse> $login */
        $login = $this->postJson('/api/login', [
            'email' => 'expired@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $twoFactorToken = $login->json('data.two_factor_token');
        $this->assertIsString($twoFactorToken);

        $this->flushSession();

        // Expire the pending challenge by clearing the cache

        Cache::forget('two-factor-pending:'.$twoFactorToken);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Your Sign-In Session Has Expired');
    }

    /*
     * Registration Integration Tests
     * ------------------------------
     */

    /**
     * Registration requires two-factor verification before a token is issued.
     */
    #[Test]
    public function it_requires_two_factor_after_registration(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Auto Enrol',
            'email' => 'autoenrol@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonStructure(['data' => ['two_factor_token']]);
        $response->assertJsonMissingPath('data.token');
        $response->assertJsonMissingPath('data.plain_text_token');
        $response->assertJsonMissingPath('data.user');
    }

    /**
     * Registration auto-enrols the user in email 2FA.
     */
    #[Test]
    public function it_auto_enrols_registered_users_in_email_2fa(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Auto Enrol',
            'email' => 'autoenrol@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();

        $user = User::query()->where('email', 'autoenrol@example.com')->firstOrFail();
        $this->assertSame(MfaMethod::Email, $user->mfa_method);
        $this->assertTrue($user->hasMfaEnabled());
    }

    /*
     * Audit Trail Tests
     * -----------------
     */

    /**
     * The two-factor flow records audit events.
     */
    #[Test]
    public function it_records_audit_events_for_two_factor_flow(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'audit@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/login', [
            'email' => 'audit@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $this->postJson('/api/two-factor/send', ['channel' => 'email']);

        // Act

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::TwoFactorIssued->value,
            'email' => 'audit@example.com',
        ]);
    }
}
