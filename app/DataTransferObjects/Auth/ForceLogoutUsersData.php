<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

/**
 * Payload for an admin force-logout request.
 */
final readonly class ForceLogoutUsersData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ForceLogoutUsersData value object.
     *
     * @param list<int> $userIds the User ids to log out everywhere
     */
    public function __construct(
        public array $userIds,
    ) {}
}
