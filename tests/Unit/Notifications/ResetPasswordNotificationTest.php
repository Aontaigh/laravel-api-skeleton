<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the reset link mail message.
 */
#[CoversClass(ResetPasswordNotification::class)]
final class ResetPasswordNotificationTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Point the link at the configured SPA reset page.
     */
    #[Test]
    public function it_builds_the_link_from_the_configured_destination(): void
    {
        // Arrange

        config(['api.password_reset_url' => 'https://app.example.com/reset-password']);

        $user = new User(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        // Act

        $mail = (new ResetPasswordNotification('token-123'))->toMail($user);

        // Assert

        $this->assertSame('Reset Your Password', $mail->subject);
        $this->assertSame(
            'https://app.example.com/reset-password?'.http_build_query([
                'token' => 'token-123',
                'email' => 'alice@example.com',
            ]),
            $mail->actionUrl,
        );
    }

    /**
     * Fall back to the API host path when no SPA destination is configured.
     */
    #[Test]
    public function it_falls_back_to_the_api_host_destination(): void
    {
        // Arrange

        config(['api.password_reset_url' => null]);

        $user = new User(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        // Act

        $mail = (new ResetPasswordNotification('token-123'))->toMail($user);

        // Assert

        $this->assertSame(
            url('/reset-password').'?'.http_build_query([
                'token' => 'token-123',
                'email' => 'alice@example.com',
            ]),
            $mail->actionUrl,
        );
    }

    /**
     * State the configured expiry window in the mail body.
     */
    #[Test]
    public function it_states_the_expiry_window(): void
    {
        // Arrange

        config(['auth.passwords.users.expire' => 90]);

        $user = new User(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        // Act

        $mail = (new ResetPasswordNotification('token-123'))->toMail($user);

        // Assert

        $this->assertStringContainsString('90 minutes', implode("\n", $mail->introLines));
    }
}
