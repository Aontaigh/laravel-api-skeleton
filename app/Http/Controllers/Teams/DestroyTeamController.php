<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\DeleteTeamAction;
use App\Enums\WebhookEvent;
use App\Events\WebhookEventDispatched;
use App\Http\Requests\Teams\DestroyTeamRequest;
use App\Models\Team;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Deletes a Team that has no assigned Users.
 *
 * @example
 * DELETE /api/teams/{team}
 */
final class DestroyTeamController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the given Team.
     *
     * @param  DestroyTeamRequest $request the validated delete request
     * @param  Team               $team    the Team being deleted (route-bound)
     * @param  DeleteTeamAction   $action  the delete Action
     * @return JsonResponse       the standardised success envelope
     */
    public function __invoke(
        DestroyTeamRequest $request,
        Team $team,
        DeleteTeamAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $teamId = $team->id;
        $teamName = $team->name;

        $action->execute($team);

        event(
            new WebhookEventDispatched(
                event: WebhookEvent::TeamDeleted,
                data: [
                    'id' => $teamId,
                    'name' => $teamName,
                ],
            ),
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Team Deleted Successfully');
    }
}
