<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

/**
 * Validated credentials for password-based login.
 */
final readonly class LoginCredentialsData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new LoginCredentialsData value object.
     *
     * @param string $email    the account email address
     * @param string $password the plaintext password to verify
     * @param bool   $remember whether the caller requested remember-me
     */
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
    ) {}
}
