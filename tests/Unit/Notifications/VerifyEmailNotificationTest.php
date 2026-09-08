<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the verification mail body and its signed link shape.
 *
 * Built with a `new User` model, never a factory: unit tests must not touch
 * the database (`UnitTestCase` fails on any query), and the notification only
 * reads `name`, `getKey()`, and the e-mail address.
 */
#[CoversClass(VerifyEmailNotification::class)]
final class VerifyEmailNotificationTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build a User instance with the fields the notification reads, without
     * touching the database.
     *
     * @param  int  $id the route-bound User ID carried in the signed URL
     * @return User the in-memory User the notification renders against
     */
    private function makeUser(int $id): User
    {
        return (new User)->forceFill([
            'id' => $id,
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Build the mail with the verify action pointing at the API's signed route.
     */
    #[Test]
    public function it_builds_the_mail_with_the_signed_route_action(): void
    {
        // Arrange

        $user = $this->makeUser(7);

        // Act

        $mail = (new VerifyEmailNotification)->toMail($user);

        // Assert

        $this->assertSame('Verify Your E-Mail Address', $mail->subject);
        $this->assertSame('Verify E-Mail Address', $mail->actionText);
        $this->assertStringContainsString('/api/auth/email/verify/7/', $mail->actionUrl);
        $this->assertStringContainsString(sha1($user->getEmailForVerification()), $mail->actionUrl);
        $this->assertStringContainsString('expires=', $mail->actionUrl);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
    }

    /**
     * Carry the configured signed-link TTL into the mail copy.
     */
    #[Test]
    public function it_states_the_configured_expiry_in_the_mail_copy(): void
    {
        // Arrange

        config(['auth.verification.expire' => 90]);
        $user = $this->makeUser(7);

        // Act

        $mail = (new VerifyEmailNotification)->toMail($user);

        // Assert

        $this->assertStringContainsString('90 minutes', implode("\n", $mail->introLines));
    }

    /**
     * Queue the notification: mail delivery must not block the request.
     */
    #[Test]
    public function it_is_queued_mail(): void
    {
        // Arrange

        $notification = new VerifyEmailNotification;

        // Act + Assert

        $this->assertContains('mail', $notification->via(new User));
        $this->assertSame(3, $notification->tries);
        $this->assertSame(30, $notification->timeout);
    }
}
