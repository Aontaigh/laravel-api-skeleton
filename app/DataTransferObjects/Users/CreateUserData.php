<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Users;

use App\Enums\RoleName;

/**
 * Validated payload for admin-initiated user creation.
 */
final readonly class CreateUserData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreateUserData value object.
     *
     * @param string      $name     the user's display name
     * @param string      $email    the user's email address
     * @param string      $password the user's initial password
     * @param RoleName    $role     the role to assign (default: User)
     * @param int|null    $teamId   the team to assign (null = no team)
     * @param string|null $phone    the optional canonical E.164 phone number
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public RoleName $role = RoleName::User,
        public ?int $teamId = null,
        public ?string $phone = null,
    ) {}
}
