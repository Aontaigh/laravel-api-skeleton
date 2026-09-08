<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth\Email;

use App\Actions\Auth\RegisterUserAction;
use App\DataTransferObjects\Auth\RegisterUserData;
use App\Enums\AuthAuditEvent;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the e-mail verification endpoints.
 *
 * The verify link is a temporary signed URL whose signature is the
 * authorisation; every tampered, expired, or replayed variant must answer
 * identically to the browser while the audit trail records the attempt.
 */
#[CoversClass(\App\Http\Controllers\Auth\Email\VerifyEmailController::class)]
#[CoversClass(\App\Http\Controllers\Auth\Email\ResendVerificationController::class)]
#[CoversClass(\App\Http\Requests\Auth\VerifyEmailRequest::class)]
#[CoversClass(\App\Http\Requests\Auth\ResendVerificationRequest::class)]
#[CoversClass(\App\Notifications\Auth\VerifyEmailNotification::class)]
#[CoversClass(\App\Actions\Auth\RegisterUserAction::class)]
final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build an unverified User and the signed verification URL the
     * notification would emit.
     *
     * @return array{0: User, 1: string} the unverified User and its signed verify URL
     */
    private function makeUnverifiedUserWithLink(): array
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'email.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        return [$user, $url];
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Verify Endpoint Tests
     * ---------------------
     */

    /**
     * Mark the e-mail verified and redirect to the SPA result page.
     */
    #[Test]
    public function it_verifies_a_valid_signed_link(): void
    {
        // Arrange

        [$user, $url] = $this->makeUnverifiedUserWithLink();

        // Act

        $response = $this->get($url);

        // Assert

        $response->assertRedirect();
        $this->assertStringContainsString('verified=1', (string) $response->headers->get('Location'));

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->email_verified_at);
    }

    /**
     * Record the `Email Verified` audit event through the queued listener.
     */
    #[Test]
    public function it_records_an_email_verified_audit_event(): void
    {
        // Arrange

        [$user, $url] = $this->makeUnverifiedUserWithLink();

        // Act

        $this->get($url);

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::EmailVerified->value,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Redirect with `verified=0` when the signed link names an unknown User.
     */
    #[Test]
    public function it_redirects_with_failure_when_the_user_is_missing(): void
    {
        // Arrange

        $url = URL::temporarySignedRoute(
            'email.verification.verify',
            now()->addMinutes(60),
            ['id' => 99999, 'hash' => sha1('nobody@example.com')],
        );

        // Act

        $response = $this->get($url);

        // Assert

        $response->assertRedirect();
        $this->assertStringContainsString('verified=0', (string) $response->headers->get('Location'));
    }

    /**
     * Invalidate a link whose signature was rewritten: the tampered variant
     * must fail the signature check before any user lookup happens.
     */
    #[Test]
    public function it_rejects_a_tampered_signature(): void
    {
        // Arrange

        [$user, $url] = $this->makeUnverifiedUserWithLink();
        $tampered = str_replace('signature=', 'signature=0', $url);

        // Act

        $response = $this->get($tampered);

        // Assert

        $response->assertForbidden();

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->email_verified_at);
    }

    /**
     * Invalidate a link built for another mailbox: the hash binds the URL to
     * the User's own e-mail, so swapping it fails the ownership compare.
     */
    #[Test]
    public function it_rejects_a_hash_bound_to_another_mailbox(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'email.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('attacker@example.com')],
        );

        // Act

        $response = $this->get($url);

        // Assert

        $response->assertRedirect();
        $this->assertStringContainsString('verified=0', (string) $response->headers->get('Location'));

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->email_verified_at);
    }

    /**
     * Redirect with success when a consumed link is replayed - verification is
     * idempotent and never downgrades a verified account.
     */
    #[Test]
    public function it_is_idempotent_when_a_valid_link_is_replayed(): void
    {
        // Arrange

        [$user, $url] = $this->makeUnverifiedUserWithLink();
        $this->get($url);

        // Act

        $response = $this->get($url);

        // Assert

        $response->assertRedirect();
        $this->assertStringContainsString('verified=1', (string) $response->headers->get('Location'));

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->email_verified_at);
    }

    /**
     * Answer an expired link with the framework's 403 signature response -
     * indistinguishable from tampering to the browser.
     */
    #[Test]
    public function it_rejects_an_expired_link(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'email.verification.verify',
            now()->subMinutes(1),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        // Act

        $response = $this->get($url);

        // Assert

        $response->assertForbidden();

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->email_verified_at);
    }

    /*
     * Resend Tests
     * ------------
     */

    /**
     * Queue a fresh verification mail for an unverified account.
     */
    #[Test]
    public function it_resends_the_link_for_an_unverified_user(): void
    {
        // Arrange

        Notification::fake();
        $user = User::factory()->unverified()->create();

        // Act

        $response = $this->actingAs($user)->postJson('/api/auth/email/resend');

        // Assert

        $response->assertOk();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    /**
     * Short-circuit without dispatching another mail once verified.
     */
    #[Test]
    public function it_does_not_resend_for_an_already_verified_user(): void
    {
        // Arrange

        Notification::fake();
        $user = User::factory()->create();

        // Act

        $response = $this->actingAs($user)->postJson('/api/auth/email/resend');

        // Assert

        $response->assertOk();
        Notification::assertNothingSentTo($user);
    }

    /**
     * Reject the resend when unauthenticated.
     */
    #[Test]
    public function it_rejects_an_unauthenticated_resend(): void
    {
        // Act

        $response = $this->postJson('/api/auth/email/resend');

        // Assert

        $response->assertUnauthorized();
    }

    /**
     * Trigger the dedicated resend limiter on repeat requests.
     */
    #[Test]
    public function it_throttles_repeat_resend_requests(): void
    {
        // Arrange

        Notification::fake();
        config(['api.email_verification_rate_limit_per_minute' => 2]);
        $user = User::factory()->unverified()->create();

        // Act

        $this->actingAs($user)->postJson('/api/auth/email/resend');
        $this->actingAs($user)->postJson('/api/auth/email/resend');
        $response = $this->actingAs($user)->postJson('/api/auth/email/resend');

        // Assert

        $response->assertTooManyRequests();
    }

    /**
     * Record the `Email Verification Sent` audit event through the pipeline.
     */
    #[Test]
    public function it_records_an_email_verification_sent_audit_event(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();

        // Act

        $this->actingAs($user)->postJson('/api/auth/email/resend');

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::EmailVerificationSent->value,
            'user_id' => $user->id,
        ]);
    }

    /*
     * Registration Trigger Tests
     * --------------------------
     */

    /**
     * Queue the verification mail when a new account registers.
     */
    #[Test]
    public function it_sends_the_verification_mail_on_registration(): void
    {
        // Arrange

        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Act

        app(RegisterUserAction::class)->execute(new RegisterUserData(
            name: 'Alice',
            email: 'alice@example.com',
            password: 'SecretPass12',
        ));

        // Assert

        $user = User::query()->where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
