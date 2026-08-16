<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Users;

/**
 * Validated input for changing the authenticated User's password.
 */
final readonly class UpdatePasswordData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdatePasswordData value object.
     *
     * @param string $currentPassword the caller's current (plain-text) password for verification
     * @param string $newPassword     the new plain-text password to set
     */
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
