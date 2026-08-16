<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\Auth\TwoFactorChallengeException;
use App\Models\User;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies a submitted two-factor code against the cached challenge.
 */
final class VerifyTwoFactorCodeAction
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Attempts allowed against a single challenge before it's torn down.
     */
    private static function maxAttempts(): int
    {
        return config()->integer('api.two_factor_max_attempts');
    }

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Verify a submitted code and consume the challenge.
     *
     * The stored value is a hash, compared with the constant-time
     * `Hash::check`. On success the code is single-use (forgotten).
     *
     * @param User        $user         the User being challenged
     * @param string      $code         the submitted six-digit code
     * @param string|null $pendingToken the opaque pending token for stateless clients
     *
     * @throws TwoFactorChallengeException when no valid challenge matches
     */
    public function execute(User $user, string $code, ?string $pendingToken = null): void
    {
        $cacheKey = IssueTwoFactorChallengeAction::cacheKey($user);

        /*
        |--------------------------------------------------------------------------
        | Lock
        |--------------------------------------------------------------------------
        |
        | Serialise verification per User so two requests carrying the same
        | correct code can't both pass `Hash::check` before either consumes the
        | challenge (single-use guarantee under concurrency).
        |
        */

        $lock = Cache::lock($cacheKey.':lock', 10);

        $acquired = $lock->get();

        if ($acquired !== true) {
            throw TwoFactorChallengeException::invalidCode();
        }

        try {
            $this->verifyUnderLock($user, $code, $cacheKey, $pendingToken);
        } finally {
            $lock->release();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Perform the verify flow while holding the per-User lock.
     *
     * @param User        $user         the User being challenged
     * @param string      $code         the submitted six-digit code
     * @param string      $cacheKey     the challenge's cache key
     * @param string|null $pendingToken the opaque pending token for stateless clients
     *
     * @throws TwoFactorChallengeException when no valid challenge matches
     */
    private function verifyUnderLock(User $user, string $code, string $cacheKey, ?string $pendingToken = null): void
    {
        $challenge = Cache::get($cacheKey);

        if (! is_array($challenge)) {
            throw TwoFactorChallengeException::invalidCode();
        }

        $attempts = is_int($challenge['attempts'] ?? null) ? $challenge['attempts'] : 0;
        $codeHash = is_string($challenge['code_hash'] ?? null) ? $challenge['code_hash'] : '';
        $expiresAt = is_int($challenge['expires_at'] ?? null)
            ? $challenge['expires_at']
            : now()->addSeconds(IssueTwoFactorChallengeAction::ttlSeconds())->timestamp;

        if (now()->timestamp > $expiresAt) {
            $this->tearDown($cacheKey, $pendingToken);

            throw TwoFactorChallengeException::invalidCode();
        }

        if ($attempts >= self::maxAttempts() || $codeHash === '') {
            $this->tearDown($cacheKey, $pendingToken);

            throw TwoFactorChallengeException::invalidCode();
        }

        /*
        |--------------------------------------------------------------------------
        | Verify
        |--------------------------------------------------------------------------
        |
        | A wrong guess re-caches with the SAME absolute expiry (never a fresh
        | TTL), so repeated attempts can't push the lifetime out. The final
        | allowed strike instead tears the challenge AND the pending session
        | down, so a resend can't reopen a fresh guess window post-lockout — the
        | visitor must re-authenticate from the login screen.
        |
        */

        if (! Hash::check($code, $codeHash)) {
            if ($attempts + 1 >= self::maxAttempts()) {
                $this->tearDown($cacheKey, $pendingToken);
            } else {
                Cache::put(
                    $cacheKey,
                    ['code_hash' => $codeHash, 'attempts' => $attempts + 1, 'expires_at' => $expiresAt],
                    Carbon::createFromTimestamp($expiresAt),
                );
            }

            throw TwoFactorChallengeException::invalidCode();
        }

        /*
        |--------------------------------------------------------------------------
        | Consume
        |--------------------------------------------------------------------------
        */

        Cache::forget($cacheKey);
    }

    /**
     * Tear the challenge down and end the pending login on lockout.
     *
     * @param string      $cacheKey     the challenge's cache key
     * @param string|null $pendingToken the opaque pending token for stateless clients
     */
    private function tearDown(string $cacheKey, ?string $pendingToken = null): void
    {
        Cache::forget($cacheKey);
        PendingTwoFactor::forget($pendingToken);
    }
}
