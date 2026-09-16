<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Provides the timing-normalisation hash used by the auth failure paths.
 *
 * Login and client-credential checks run a dummy `Hash::check()` when the
 * account is unknown, so a missing account costs the same as a wrong password
 * and cannot be told apart by response time. That dummy needs a real hash of
 * the configured driver.
 *
 * The hash is resolved lazily and memoised per process: computing it in
 * `AppServiceProvider::boot()` would pay a full Argon2id hash on every
 * request and queued job, including the many that never authenticate at all.
 * A configured `api.auth_timing_normalisation_hash` still takes precedence, so
 * operators can pin a value and skip even the first computation - an
 * unpinned, lazily generated hash is measurably slower than a wrong-password
 * attempt on its first use, which would leak that the account does not exist.
 */
final class AuthTimingHash
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * Memoised placeholder hash for the current process.
     *
     * Null until the first authentication failure that needs it.
     */
    private static ?string $hash = null;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the timing-normalisation hash, computing it once per process.
     *
     * @return string the configured hash, or a freshly generated placeholder
     */
    public static function value(): string
    {
        $configured = config('api.auth_timing_normalisation_hash');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return self::$hash ??= Hash::make('auth-timing-normalisation');
    }
}
