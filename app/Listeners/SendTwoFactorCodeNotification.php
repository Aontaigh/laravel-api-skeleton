<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TwoFactorChallengeIssued;
use App\Notifications\Auth\TwoFactorCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Delivers the two-factor code email off the request hot path.
 */
final class SendTwoFactorCodeNotification implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the queued listener may run before timing out.
     */
    public int $timeout = 30;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Send the two-factor code notification for the issued challenge.
     *
     * @param TwoFactorChallengeIssued $event the dispatched challenge event
     */
    public function handle(TwoFactorChallengeIssued $event): void
    {
        $event->user->notify(new TwoFactorCodeNotification($event->code));
    }
}
