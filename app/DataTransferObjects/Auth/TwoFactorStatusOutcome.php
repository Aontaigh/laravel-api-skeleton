<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

/**
 * Result of resolving a pending two-factor challenge for status polling.
 */
final readonly class TwoFactorStatusOutcome
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new TwoFactorStatusOutcome value object.
     *
     * @param string|null $expiresAt    the serialised expiry timestamp on success
     * @param string|null $errorMessage the client-facing error message on failure
     * @param int|null    $statusCode   the HTTP status code on failure
     */
    private function __construct(
        public ?string $expiresAt,
        public ?string $errorMessage,
        public ?int $statusCode,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the pending challenge is still active.
     *
     * @return bool true when the outcome carries a serialised expiry timestamp
     */
    public function isSuccess(): bool
    {
        return $this->errorMessage === null;
    }

    /**
     * Build a success outcome for an active pending challenge.
     *
     * @param  string $expiresAt the serialised expiry timestamp
     * @return self   the success outcome
     */
    public static function success(string $expiresAt): self
    {
        return new self(
            expiresAt: $expiresAt,
            errorMessage: null,
            statusCode: null,
        );
    }

    /**
     * Build a failure outcome when the pending challenge is missing or expired.
     *
     * @return self the session-expired outcome
     */
    public static function sessionExpired(): self
    {
        return new self(
            expiresAt: null,
            errorMessage: 'Your Sign-In Session Has Expired',
            statusCode: 422,
        );
    }

    /**
     * Build a failure outcome when the pending User is suspended.
     *
     * @return self the account-suspended outcome
     */
    public static function accountSuspended(): self
    {
        return new self(
            expiresAt: null,
            errorMessage: 'Account Suspended',
            statusCode: 403,
        );
    }
}
