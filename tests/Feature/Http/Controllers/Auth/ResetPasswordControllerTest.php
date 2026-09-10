<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\ResetUserPasswordAction;
use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeOtherWebSessionsForUserAction;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Models\WebSession;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Support\ApiResponse;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the password reset endpoint.
 *
 * A valid token must rotate every credential derived from the old password;
 * an invalid, mismatched, replayed, or expired token must never mutate state.
 */
#[CoversClass(ResetPasswordController::class)]
#[CoversClass(ResetPasswordRequest::class)]
#[CoversClass(ResetUserPasswordAction::class)]
#[CoversClass(RevokeOtherWebSessionsForUserAction::class)]
#[CoversClass(InvalidateStoredSessionAction::class)]
#[CoversClass(PasswordChangedNotification::class)]
#[CoversClass(ApiResponse::class)]
final class ResetPasswordControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var User the User recovering via the reset link */
    private User $user;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Assert a generic rejection that leaves the User untouched.
     *
     * The 422 must not distinguish an unknown token from an expired one.
     *
     * @param  TestResponse<JsonResponse> $response the reset response under test
     * @return void
     */
    private function assertResetRejected(TestResponse $response): void
    {
        $response->assertUnprocessable();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('message', 'The Reset Token Is Invalid Or Has Expired');
        $fresh = $this->user->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue(Hash::check('OldPassword#1', $fresh->password));
        Notification::assertNotSentTo($this->user, PasswordChangedNotification::class);
    }

    /**
     * Issue a password-reset token for the given User.
     *
     * `Password::broker()` is typed as the contract; the concrete broker exposes
     * `createToken()` for tests that need a known-good token without HTTP.
     *
     * @return string the issued reset token
     */
    private function resetTokenFor(User $user): string
    {
        $broker = Password::broker();
        if (! $broker instanceof PasswordBroker) {
            throw new \RuntimeException('Password Broker Is Not the Expected Concrete Implementation');
        }

        return $broker->createToken($user);
    }

    /**
     * Create the User, a Personal Access Token, and two registered sessions.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->user = User::factory()->create([
            'password' => Hash::make('OldPassword#1'),
        ]);

        $this->user->createToken('Leaked Bearer Token');

        WebSession::factory()->count(2)->create(['user_id' => $this->user->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Mutation Tests
     * --------------
     */

    /**
     * Reset the password and rotate every derived credential.
     */
    #[Test]
    public function it_resets_the_password_and_rotates_every_credential(): void
    {
        // Arrange

        $token = $this->resetTokenFor($this->user);

        /** @var int $versionBefore */
        $versionBefore = $this->user->session_version;
        $rememberBefore = $this->user->remember_token;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', 'Password Reset Successfully');

        $fresh = $this->user->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue(Hash::check('Xq7#mK2$vL9pTzW4', $fresh->password));
        $this->assertNotSame($rememberBefore, $fresh->remember_token);
        $this->assertSame($versionBefore + 1, $fresh->session_version);
        $this->assertSame(0, $fresh->tokens()->count());

        $this->assertSame(
            0,
            WebSession::query()
                ->where('user_id', $this->user->id)
                ->whereNull('revoked_at')
                ->count(),
        );

        Notification::assertSentTo($this->user, PasswordChangedNotification::class);
        Notification::assertNotSentTo($this->user, ResetPasswordNotification::class);
    }

    /**
     * Record the reset in the auth audit log.
     */
    #[Test]
    public function it_records_a_password_reset_audit_event(): void
    {
        // Arrange

        $token = $this->resetTokenFor($this->user);

        // Act

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => 'Password Reset',
            'user_id' => $this->user->id,
            'email' => $this->user->email,
        ]);
    }

    /*
     * Token Abuse Tests
     * -----------------
     */

    /**
     * Reject a token that was never issued.
     */
    #[Test]
    public function it_rejects_an_unknown_token(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $this->user->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertResetRejected($response);

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => 'Password Reset Failed',
            'email' => $this->user->email,
        ]);
    }

    /**
     * Reject a valid token presented with a different email address.
     */
    #[Test]
    public function it_rejects_a_token_presented_for_another_address(): void
    {
        // Arrange

        /** @var User $other */
        $other = User::factory()->create(['password' => Hash::make('OldPassword#1')]);

        $token = $this->resetTokenFor($this->user);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $other->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertResetRejected($response);

        $otherFresh = $other->fresh();

        $this->assertNotNull($otherFresh);
        $this->assertTrue(Hash::check('OldPassword#1', $otherFresh->password));
    }

    /**
     * Reject a token that has already been consumed.
     */
    #[Test]
    public function it_rejects_a_replayed_token(): void
    {
        // Arrange

        $token = $this->resetTokenFor($this->user);
        $payload = [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ];

        $this->postJson('/api/auth/reset-password', $payload);

        // Act

        /** @var TestResponse<JsonResponse> $replay */
        $replay = $this->postJson('/api/auth/reset-password', $payload);

        // Assert

        $replay->assertUnprocessable();
        $replay->assertJsonPath('message', 'The Reset Token Is Invalid Or Has Expired');
        /*
         * The only alert is the one from the first, legitimate reset - the
         * replay itself must not have produced a second rotation or alert.
         */

        Notification::assertSentToTimes($this->user, PasswordChangedNotification::class, 1);

        /*
         * The first reset must be the one that persisted: the replay consumed
         * nothing, so the password is still the value from the first request.
         */

        $fresh = $this->user->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue(Hash::check('Xq7#mK2$vL9pTzW4', $fresh->password));
    }

    /**
     * Reject a token that has passed the configured expiry window.
     */
    #[Test]
    public function it_rejects_an_expired_token(): void
    {
        // Arrange

        $token = $this->resetTokenFor($this->user);

        DB::table(config()->string('auth.passwords.users.table'))
            ->where('email', $this->user->email)
            ->update(['created_at' => now()->subHours(2)]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertResetRejected($response);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject a new password that fails the shared password policy.
     */
    #[Test]
    public function it_rejects_a_password_that_fails_the_policy(): void
    {
        // Arrange

        $token = $this->resetTokenFor($this->user);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'short',
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['password']);

        $fresh = $this->user->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue(Hash::check('OldPassword#1', $fresh->password));
    }

    /**
     * Reject an oversized reset token before any broker lookup runs.
     */
    #[Test]
    public function it_rejects_an_oversized_reset_token(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => str_repeat('a', 100),
            'email' => $this->user->email,
            'password' => 'NewSecretPass13',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.errors.token.0', 'Reset Token Is Too Long');
    }
}
