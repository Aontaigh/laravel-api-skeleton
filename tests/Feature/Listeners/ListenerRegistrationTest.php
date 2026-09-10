<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Events\TwoFactorChallengeIssued;
use App\Listeners\RecordAuthAuditLog;
use App\Listeners\SendTwoFactorCodeNotification;
use App\Models\User;
use App\Notifications\Auth\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for event listener registration.
 *
 * Regression guard: event discovery (app/Listeners) and manual
 * `Event::listen()` calls both registered the queued listeners, so every
 * audit event was persisted twice and every two-factor e-mail queued twice.
 * Discovery is the single source of registration.
 */
#[CoversClass(RecordAuthAuditLog::class)]
#[CoversClass(SendTwoFactorCodeNotification::class)]
final class ListenerRegistrationTest extends TestCase
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
     * Register exactly one listener per queued auth event.
     */
    #[Test]
    public function it_registers_exactly_one_listener_per_auth_event(): void
    {
        $this->assertSame(1, count(Event::getListeners(AuthEventOccurred::class)));
        $this->assertSame(1, count(Event::getListeners(TwoFactorChallengeIssued::class)));
    }

    /**
     * Persist exactly one audit row per dispatched auth event.
     */
    #[Test]
    public function it_records_exactly_one_audit_row_per_event(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        event(new AuthEventOccurred(new RecordAuthAuditData(
            event: AuthAuditEvent::Register,
            userId: $user->id,
            email: $user->email,
        )));

        // Assert

        $this->assertSame(
            1,
            DB::table('auth_audit_logs')
                ->where('email', $user->email)
                ->where('event', 'Register')
                ->count(),
        );
    }

    /**
     * Queue exactly one two-factor code e-mail per issued challenge.
     */
    #[Test]
    public function it_queues_exactly_one_code_notification_per_challenge(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        TwoFactorChallengeIssued::dispatch($user, '123456');

        // Assert

        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
    }
}
