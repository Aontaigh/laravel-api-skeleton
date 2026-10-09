<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Sessions;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Validated filter inputs for Web Session list queries.
 */
final readonly class SessionFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new SessionFilters value object.
     *
     * @param User                 $viewer        the authenticated User (drives row scoping)
     * @param bool                 $listsAllUsers whether the viewer may see every User's sessions
     * @param string|null          $search        optional device, IP, or user-agent search term
     * @param list<int>            $userIds       optional owner filters for admin viewers
     * @param CarbonImmutable|null $from          optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to            optional inclusive `created_at` upper bound
     */
    public function __construct(
        public User $viewer,
        public bool $listsAllUsers = false,
        public ?string $search = null,
        public array $userIds = [],
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
