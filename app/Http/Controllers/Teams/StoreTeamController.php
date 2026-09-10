<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamAction;
use App\DataTransferObjects\Teams\CreateTeamData;
use App\Enums\WebhookEvent;
use App\Events\WebhookEventDispatched;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Creates a new Team.
 *
 * @example
 * POST /api/teams {"name":"Engineering"}
 */
final class StoreTeamController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a Team.
     *
     * @param  StoreTeamRequest $request the validated creation request
     * @param  CreateTeamAction $action  the create-team Action
     * @return JsonResponse     the standardised success envelope
     */
    public function __invoke(
        StoreTeamRequest $request,
        CreateTeamAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new CreateTeamData(
            name: $input->string('name')->toString(),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $team = $action->execute($data);

        event(
            new WebhookEventDispatched(
                event: WebhookEvent::TeamCreated,
                data: [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ),
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new TeamResource($team),
            message: 'Team Created Successfully',
            statusCode: 201,
        );
    }
}
