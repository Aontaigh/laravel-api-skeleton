<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bounds every issued token's lifetime to the configured maximum.
 *
 * The machine credential contract is that a token always expires and never
 * outlives the environment's ceiling. A configured lifetime of zero or below
 * would otherwise mean "never expires", so it falls back to the maximum
 * instead of disabling expiration.
 */
final class TokenLifetime
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * The configured ceiling on a token's lifetime, in days.
     *
     * Never returns below one: a ceiling of zero or below would make every
     * configured lifetime non-expiring, which the contract forbids.
     *
     * @return int the maximum lifetime in whole days, at least one
     */
    public static function maximumDays(): int
    {
        return max(1, config()->integer('api.token_max_expiration_days'));
    }

    /**
     * Bound a configured lifetime to a positive value within the ceiling.
     *
     * A value above the maximum is capped to it; a value of zero or below,
     * which asks for a token that never expires, resolves to the maximum so a
     * token is never issued without an expiry.
     *
     * @param  int $days the configured lifetime in days
     * @return int the bounded lifetime in whole days
     */
    public static function boundedConfiguredDays(int $days): int
    {
        $maximum = self::maximumDays();

        return $days > 0 ? min($days, $maximum) : $maximum;
    }
}
