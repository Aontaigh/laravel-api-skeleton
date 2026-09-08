<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a Team that has no assigned Users.
 */
final class DeleteTeamAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the given Team.
     *
     * A Team with assigned Users is never deleted: dropping it would silently
     * un-scope every member to `team_id` null (see the `nullOnDelete` foreign
     * key on `users.team_id`), changing what Managers may see without any
     * explicit reassignment. Reassign or remove the members first.
     *
     * @example
     * app(DeleteTeamAction::class)->execute($team);
     *
     * @param  Team $team the Team to delete
     * @return void
     *
     * @throws ValidationException when the Team still has assigned Users
     */
    public function execute(Team $team): void
    {
        if ($team->users()->exists()) {
            throw ValidationException::withMessages([
                'team' => 'Team Has Assigned Users',
            ]);
        }

        $team->delete();
    }
}
