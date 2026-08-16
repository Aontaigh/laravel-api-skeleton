<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals that a fresh two-factor code was generated and should be delivered.
 *
 * Dispatched synchronously by {@see IssueTwoFactorChallengeAction}; the queued
 * listener sends the email off the request hot path.
 */
final class TwoFactorChallengeIssued
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use Dispatchable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param User   $user the User being challenged
     * @param string $code the plaintext six-digit code
     */
    public function __construct(
        public readonly User $user,
        public readonly string $code,
    ) {}
}
