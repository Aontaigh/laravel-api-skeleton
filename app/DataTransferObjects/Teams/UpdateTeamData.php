<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Teams;

use App\Models\Team;

/**
 * Validated input for updating a Team.
 */
final readonly class UpdateTeamData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdateTeamData value object.
     *
     * @param Team   $team the Team being updated
     * @param string $name the new display name
     */
    public function __construct(
        public Team $team,
        public string $name,
    ) {}
}
