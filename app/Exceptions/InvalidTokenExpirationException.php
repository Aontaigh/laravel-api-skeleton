<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a requested token lifetime is missing or beyond the configured maximum.
 *
 * A token with no expiry cannot be aged out when a credential leaks or its
 * owner leaves, and a lifetime beyond the ceiling is asked for rather than
 * trusted - both are refused before the token is minted.
 */
final class InvalidTokenExpirationException extends InvalidArgumentException
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new InvalidTokenExpirationException.
     *
     * @param string   $message     the Title Case user-facing message
     * @param int|null $maximumDays the configured ceiling in days, when the failure is an over-long request
     */
    private function __construct(
        string $message,
        public readonly ?int $maximumDays = null,
    ) {
        parent::__construct($message);
    }

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * A token asked for without an expiry - a credential that never ages out.
     *
     * @return self the refusal for a non-expiring token
     */
    public static function nonExpiring(): self
    {
        return new self('Tokens Must Expire');
    }

    /**
     * A token asked to live longer than the configured ceiling.
     *
     * @param  int  $maximumDays the configured ceiling in days
     * @return self the refusal naming the ceiling
     */
    public static function beyondMaximum(int $maximumDays): self
    {
        return new self('Token Expiry Exceeds The Maximum Lifetime', $maximumDays);
    }
}
