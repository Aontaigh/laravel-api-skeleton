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
    public static function generic(): self
    {
        return self::withMessages([
            'email' => ['Invalid Credentials'],
        ]);
    }
}
