<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Teams;

/**
 * Validated input for creating a Team.
 */
final readonly class CreateTeamData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreateTeamData value object.
     *
     * @param string $name the Team display name
     */
    public function __construct(
        public string $name,
    ) {}
}
