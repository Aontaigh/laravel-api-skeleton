<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\DataTransferObjects\Teams\UpdateTeamData;
use App\Models\Team;

/**
 * Updates a Team's attributes.
 */
final class UpdateTeamAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the validated changes and return the refreshed Team.
     *
     * @example
     * app(UpdateTeamAction::class)->execute($data);
     *
     * @param  UpdateTeamData $data the validated update payload
     * @return Team           the refreshed Team
     */
    public function execute(UpdateTeamData $data): Team
    {
        $data->team->update([
            'name' => $data->name,
        ]);

        return $data->team->refresh();
    }
}
