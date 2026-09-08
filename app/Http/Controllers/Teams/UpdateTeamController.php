<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\UpdateTeamAction;
use App\DataTransferObjects\Teams\UpdateTeamData;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates a Team.
 *
 * @example
 * PATCH /api/teams/{team} {"name": "Updated Name"}
 */
final class UpdateTeamController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Update the given Team.
     *
     * @param  UpdateTeamRequest $request the validated update request
     * @param  Team              $team    the Team being updated (route-bound)
     * @param  UpdateTeamAction  $action  the update Action
     * @return JsonResponse      the standardised success envelope
     */
    public function __invoke(
        UpdateTeamRequest $request,
        Team $team,
        UpdateTeamAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new UpdateTeamData(
            team: $team,
            name: $input->string('name')->toString(),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $updatedTeam = $action->execute($data);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new TeamResource($updatedTeam),
            message: 'Team Updated Successfully',
        );
    }
}
