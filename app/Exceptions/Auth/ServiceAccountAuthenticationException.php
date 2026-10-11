<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Illuminate\Validation\ValidationException;

/**
 * A service account attempted a password sign-in.
 *
 * Machine identities have no interactive password surface: the attempt is a
 * deliberate policy refusal, so the audit trail can label it `refused`
 * instead of `failed` while the HTTP response stays the same generic 422 the
 * enumeration hardening requires.
 */
final class ServiceAccountAuthenticationException extends ValidationException
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Build the generic invalid-credentials failure for a machine identity.
     *
     * The message deliberately matches the ordinary invalid-credentials
     * response so a password attempt on a service account reveals nothing
     * beyond the refusal the audit trail already records.
     *
     * @return self the populated validation failure
     */
    public static function generic(): self
    {
        return self::withMessages([
            'email' => ['Invalid Credentials'],
        ]);
    }
}
