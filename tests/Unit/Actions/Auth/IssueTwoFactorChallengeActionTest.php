<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\IssueTwoFactorChallengeAction;
use App\Events\TwoFactorChallengeIssued;
use App\Models\User;
use App\Notifications\Auth\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for IssueTwoFactorChallengeAction.
 */
#[CoversClass(IssueTwoFactorChallengeAction::class)]
final class IssueTwoFactorChallengeActionTest extends TestCase
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
     * Cache a hashed code and dispatch the delivery event.
     */
    #[Test]
    public function it_caches_a_hashed_code_and_dispatches_the_delivery_event(): void
    {
        // Arrange

        Event::fake([TwoFactorChallengeIssued::class]);

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(IssueTwoFactorChallengeAction::class)->execute($user);

        // Assert

        $challenge = Cache::get(IssueTwoFactorChallengeAction::cacheKey($user));
        $this->assertIsArray($challenge);
        $this->assertArrayHasKey('code_hash', $challenge);
        $this->assertSame(0, $challenge['attempts']);
        $this->assertArrayHasKey('expires_at', $challenge);

        Event::assertDispatched(TwoFactorChallengeIssued::class, function (TwoFactorChallengeIssued $event) use ($user): bool {
            return $event->user->is($user) && strlen($event->code) === 6;
        });
    }

    /**
     * Carry the existing attempt count when re-issuing a code.
     */
    #[Test]
    public function it_preserves_the_attempt_count_when_reissuing_a_code(): void
    {
        // Arrange

        Event::fake([TwoFactorChallengeIssued::class]);

        /** @var User $user */
        $user = User::factory()->create();

        Cache::put(IssueTwoFactorChallengeAction::cacheKey($user), [
            'code_hash' => 'hash',
            'attempts' => 3,
            'expires_at' => now()->addMinute()->timestamp,
        ], 300);

        // Act

        app(IssueTwoFactorChallengeAction::class)->execute($user, preserveAttempts: true);

        // Assert

        $challenge = Cache::get(IssueTwoFactorChallengeAction::cacheKey($user));
        $this->assertIsArray($challenge);
        $this->assertSame(3, $challenge['attempts']);
    }

    /**
     * Deliver the code notification when the queued listener handles the event.
     */
    #[Test]
    public function it_delivers_the_code_notification_via_the_queued_listener(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(IssueTwoFactorChallengeAction::class)->execute($user);

        // Assert

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }
}
