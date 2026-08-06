<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Users;

use App\Models\User;

/**
 * Validated input for updating a User.
 */
final readonly class UpdateUserData
{
    /*
    |--------------------------------------------------------------------------​
    | Constructor
    |--------------------------------------------------------------------------​
    */

    /**
     * Create a new UpdateUserData value object.
     *
     * @param User        $user   the User being updated
     * @param string|null $name   the new display name, or null to leave unchanged
     * @param int|null    $teamId the new Team id, or null to leave unchanged
     */
    public function __construct(
        public User $user,
        public ?string $name = null,
        public ?int $teamId = null,
    ) {}
}
