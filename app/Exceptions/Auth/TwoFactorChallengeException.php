<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Thrown when a two-factor challenge cannot be resolved or verified.
 */
final class TwoFactorChallengeException extends RuntimeException
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * A single generic message for every failure mode.
     *
     * The response cannot distinguish "no challenge" from "wrong code" from
     * "too many attempts", so a probing caller learns nothing about which
     * rejection it hit - enumeration hardening for the two-factor gate.
    /**
     * A single generic message for every failure mode.
     * The response cannot distinguish "no challenge" from "wrong code" from
     * "too many attempts", so a probing caller learns nothing about which
     * rejection it hit - enumeration hardening for the two-factor gate.
     */
    private const string GENERIC_MESSAGE = 'Invalid or Expired Code';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * No pending two-factor challenge exists for the session.
     *
     * @return self the exception
     */
    public static function noChallenge(): self
    {
        return new self(self::GENERIC_MESSAGE);
    }

    /**
     * The submitted code was wrong, expired, or the attempt cap was hit.
     *
     * @return self the exception
     */
    public static function invalidCode(): self
    {
        return new self(self::GENERIC_MESSAGE);
    }
}
