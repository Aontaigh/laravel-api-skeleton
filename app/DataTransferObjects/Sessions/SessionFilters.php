<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Sessions;

use App\Models\User;

/**
 * Validated filter inputs for Web Session list queries.
 */
final readonly class SessionFilters
{
    /**
     * Create a new SessionFilters value object.
     *
     * @param User        $viewer        the authenticated User (drives row scoping)
     * @param bool        $listsAllUsers whether the viewer may see every User's sessions
     * @param string|null $search        optional device, IP, or user-agent search term
     * @param int|null    $userId        optional owner filter for admin viewers
     */
    public function __construct(
        public User $viewer,
        public bool $listsAllUsers = false,
        public ?string $search = null,
        public ?int $userId = null,
    ) {}
}
