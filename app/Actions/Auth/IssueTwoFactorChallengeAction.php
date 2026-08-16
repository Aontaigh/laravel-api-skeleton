<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Events\TwoFactorChallengeIssued;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Generates, caches (hashed), and emails a fresh six-digit two-factor code.
 */
final class IssueTwoFactorChallengeAction
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Cache key prefix for the pending challenge.
     */
    public const string CACHE_PREFIX = 'two-factor:';

    /**
     * Exclusive upper bound for the six-digit code (100000..999999 range).
     */
    private const int CODE_UPPER_BOUND = 999999;

    /**
     * Inclusive lower bound so the code always renders as six digits.
     */
    private const int CODE_LOWER_BOUND = 100000;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Seconds the code remains valid before auto-expiry.
     */
    public static function ttlSeconds(): int
    {
        return config()->integer('api.two_factor_code_ttl_seconds');
    }

    /**
     * Whether a code challenge already exists for the User.
     *
     * @param  User $user the challenged User
     * @return bool true when a cached challenge is present
     */
    public static function hasChallenge(User $user): bool
    {
        return Cache::has(self::cacheKey($user));
    }

    /**
     * Generate, cache (hashed), and e-mail a fresh six-digit code.
     *
     * A fresh issue overwrites any previous code, invalidating it. Delivery is
     * queued via {@see TwoFactorChallengeIssued}. A resend passes
     * `preserveAttempts` so the guess count carries over the new code — a
     * caller can't reset the lockout window by requesting a fresh code.
     *
     * @param User $user             the User to challenge
     * @param bool $preserveAttempts whether to carry the existing attempt count onto the new code
     */
    public function execute(User $user, bool $preserveAttempts = false): void
    {
        $code = (string) random_int(self::CODE_LOWER_BOUND, self::CODE_UPPER_BOUND);

        Cache::put(
            self::cacheKey($user),
            [
                'code_hash' => Hash::make($code),
                'attempts' => $preserveAttempts ? $this->currentAttempts($user) : 0,
                'expires_at' => now()->addSeconds(self::ttlSeconds())->timestamp,
            ],
            self::ttlSeconds(),
        );

        TwoFactorChallengeIssued::dispatch($user, $code);
    }

    /**
     * Build the cache key for a User's pending challenge.
     *
     * @param  User   $user the User
     * @return string the cache key
     */
    public static function cacheKey(User $user): string
    {
        return self::CACHE_PREFIX.$user->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Read the attempt count of the User's current challenge, if any.
     *
     * @param  User $user the challenged User
     * @return int  the stored attempt count, or zero when no challenge exists
     */
    private function currentAttempts(User $user): int
    {
        $challenge = Cache::get(self::cacheKey($user));

        return is_array($challenge) && is_int($challenge['attempts'] ?? null)
            ? $challenge['attempts']
            : 0;
    }
}
