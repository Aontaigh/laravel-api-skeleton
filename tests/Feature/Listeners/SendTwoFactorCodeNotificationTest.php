<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\TwoFactorChallengeIssued;
use App\Listeners\SendTwoFactorCodeNotification;
use App\Models\User;
use App\Notifications\Auth\TwoFactorCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the queued two-factor code notification listener.
 */
#[CoversClass(TwoFactorChallengeIssued::class)]
#[CoversClass(SendTwoFactorCodeNotification::class)]
final class SendTwoFactorCodeNotificationTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Send the two-factor code notification when the event is handled.
     */
    #[Test]
    public function it_sends_the_two_factor_code_notification(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(SendTwoFactorCodeNotification::class)->handle(
            new TwoFactorChallengeIssued($user, '123456'),
        );

        // Assert

        Notification::assertSentTo(
            $user,
            TwoFactorCodeNotification::class,
            function (TwoFactorCodeNotification $notification) use ($user): bool {
                $mail = $notification->toMail($user);

                return str_contains((string) ($mail->introLines[0] ?? ''), '123456');
            },
        );
    }

    /**
     * Register the listener as a queued handler for the challenge event.
     */
    #[Test]
    public function it_is_registered_as_a_queued_listener(): void
    {
        // Assert

        $this->assertContains(ShouldQueue::class, class_implements(SendTwoFactorCodeNotification::class));

        $listeners = Event::getListeners(TwoFactorChallengeIssued::class);
        $this->assertNotEmpty($listeners);
    }
}
