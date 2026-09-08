<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\DataTransferObjects\Teams\CreateTeamData;
use App\Models\Team;

/**
 * Creates a Team.
 */
final class CreateTeamAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Persist a new Team.
     *
     * @example
     * app(CreateTeamAction::class)->execute($data);
     *
     * @param  CreateTeamData $data the validated creation payload
     * @return Team           the created Team
     */
    public function execute(CreateTeamData $data): Team
    {
        /** @var Team $team */
        $team = Team::query()->create([
            'name' => $data->name,
        ]);

        return $team;
    }
}
