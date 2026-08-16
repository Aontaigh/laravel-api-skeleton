<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Http\Requests\Users\MeShowRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\IndexFieldsQuery;
use App\Queries\Users\UserIncludeQuery;
use App\Queries\Users\UserQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns the authenticated User's own profile.
 *
 * @example
 * GET /api/me?fields[users]=id,name&include=team,role
 */
final class MeShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Me Show Controller.
     *
     * @param IndexFieldsQuery $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param UserIncludeQuery $includeQuery composes validated includes onto any User builder
     */
    public function __construct(
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly UserIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the authenticated User with optional sparse fieldsets and includes.
     *
     * @param  MeShowRequest $request the validated profile request
     * @return JsonResponse  the standard API success envelope
     */
    public function __invoke(MeShowRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $user = $request->viewer();

        $includes = $request->includes();
        $userFields = $request->userFields();
        $teamFields = $request->teamFields();
        $roleFields = $request->roleFields();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        $query = User::query()->whereKey($user->getKey());

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $userFields,
            allowedFields: $request->allowedUserFields(),
            table: 'users',
            requiredColumns: UserQueryConstraints::requiredSelectColumns($includes),
        );

        $this->includeQuery->apply($query, $includes, $teamFields, $roleFields);

        $loadedUser = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new UserResource($loadedUser),
            message: 'Profile Retrieved Successfully',
        );
    }
}
