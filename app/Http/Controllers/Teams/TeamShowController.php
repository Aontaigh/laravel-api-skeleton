<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Requests\Teams\TeamShowRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Queries\IndexFieldsQuery;
use App\Queries\Teams\TeamQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a single Team.
 *
 * @example
 * GET /api/teams/{team}?fields[teams]=id,name
 */
final class TeamShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param IndexFieldsQuery $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the route-bound Team with optional sparse fieldsets.
     *
     * @param  TeamShowRequest $request the validated show request
     * @param  Team            $team    the route-bound Team
     * @return JsonResponse    the standardised success envelope
     */
    public function __invoke(
        TeamShowRequest $request,
        Team $team,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $teamFields = $request->teamFields();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<Team> $query */
        $query = Team::query()->whereKey($team->getKey());

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $teamFields,
            allowedFields: TeamQueryConstraints::ALLOWED_FIELDS,
            table: TeamQueryConstraints::TABLE,
            requiredColumns: TeamQueryConstraints::requiredSelectColumns(),
        );

        /** @var Team $loadedTeam */
        $loadedTeam = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new TeamResource($loadedTeam),
            message: 'Team Retrieved Successfully',
        );
    }
}
