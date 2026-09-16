<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Distinguishes a real Personal Access Token from a session token.
 *
 * Sanctum's `HasApiTokens::currentAccessToken()` is declared as a non-null
 * `TToken`, but at runtime it is null for unauthenticated requests and a
 * `TransientToken` for cookie-session callers. Accepting the honest union as a
 * parameter lets callers test for a stateless token without PHPStan's
 * "always true" narrowing on the framework's inaccurate return type.
 */
final class PresentingToken
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the presenting token is a persisted Personal Access Token.
     *
     * A `TransientToken` (cookie session) or `null` (no credential) must never
     * be treated as a stateless caller - only a database-backed token is.
     *
     * @param  PersonalAccessToken|TransientToken|null $token the token Sanctum resolved for the current request
     * @return bool                                    true when the caller presents a persisted Personal Access Token
     */
    public static function isPersonalAccessToken(PersonalAccessToken|TransientToken|null $token): bool
    {
        return $token instanceof PersonalAccessToken;
    }
}
