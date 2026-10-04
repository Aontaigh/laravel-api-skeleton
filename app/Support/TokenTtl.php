<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Derives the response TTL for an issued token.
 *
 * The value is read from the token's own `expires_at`, never recomputed from
 * configuration: the field is what Sanctum enforces at guard time, and any
 * time elapsed between minting and rendering would otherwise make a
 * config-derived window overstate the remaining life.
 */
final class TokenTtl
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Seconds remaining on the token, clamped at zero; null when the token
     * never expires.
     *
     * @param  CarbonInterface|null $expiresAt the token's own expiry, or null for a never-expiring token
     * @param  CarbonInterface      $now       the instant to measure the remaining life from
     * @return int|null             the whole seconds of remaining life, or null
     */
    public static function secondsFor(?CarbonInterface $expiresAt, CarbonInterface $now): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        return max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
    }
}
