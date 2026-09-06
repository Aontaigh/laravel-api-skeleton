<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\PasswordChangeSource;
use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Services\UserAgent\BasicUserAgentParser;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the password-changed security alert.
 */
#[CoversClass(PasswordChangedNotification::class)]
#[CoversClass(PasswordChangeSource::class)]
final class PasswordChangedNotificationTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Describe a reset-link recovery with request details and a next step.
     */
    #[Test]
    public function it_describes_a_reset_link_recovery(): void
    {
        // Arrange

        config([
            'api.password_forgot_url' => 'https://app.example.com/forgot-password',
            'app.timezone' => 'UTC',
        ]);

        $user = new User(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        // Act

        $mail = (new PasswordChangedNotification(
            source: PasswordChangeSource::ResetLink,
            ipAddress: '203.0.113.10',
            userAgent: 'Mozilla/5.0 (Macintosh) Chrome/120.0',
            changedAt: Carbon::create(2026, 9, 6, 12, 0, 0, 'UTC'),
            userAgentParser: new BasicUserAgentParser,
        ))->toMail($user);

        // Assert

        $body = implode("\n", $mail->introLines);

        $this->assertSame('Your Password Was Changed', $mail->subject);
        $this->assertSame('https://app.example.com/forgot-password', $mail->actionUrl);
        $this->assertStringContainsString('A Password Reset Link', $body);
        $this->assertStringContainsString('IP 203.0.113.10', $body);
        $this->assertStringContainsString('6 September 2026', $body);
    }

    /**
     * Show `Unknown` for missing request details instead of empty gaps.
     */
    #[Test]
    public function it_shows_unknown_for_missing_request_details(): void
    {
        // Arrange

        $user = new User(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        // Act

        $mail = (new PasswordChangedNotification(
            source: PasswordChangeSource::SelfService,
            userAgentParser: new BasicUserAgentParser,
        ))->toMail($user);

        // Assert

        $body = implode("\n", $mail->introLines);

        $this->assertStringContainsString('Your Account Security Settings', $body);
        $this->assertStringContainsString('IP Unknown', $body);
        $this->assertSame(url('/forgot-password'), $mail->actionUrl);
    }

    /**
     * Keep the display labels distinct per change source.
     */
    #[Test]
    public function it_labels_every_change_source(): void
    {
        $this->assertSame('Your Account Security Settings', PasswordChangeSource::SelfService->label());
        $this->assertSame('A Password Reset Link', PasswordChangeSource::ResetLink->label());
    }
}
