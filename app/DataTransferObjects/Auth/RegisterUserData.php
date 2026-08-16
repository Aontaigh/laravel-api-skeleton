<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

/**
 * Validated input for self-service User registration.
 */
final readonly class RegisterUserData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RegisterUserData value object.
     *
     * @param string $name     the display name
     * @param string $email    the unique email address
     * @param string $password the hashed password is applied by the Model cast
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
