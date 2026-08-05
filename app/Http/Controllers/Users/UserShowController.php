<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Http\Requests\Users\UserShowRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\ListFieldsQuery;
use App\Queries\Users\UserIncludeQuery;
use App\Queries\Users\UserQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a single User by ID.
 *
 * @example
 * GET /api/users/{user}?fields[users]=id,name&include=team,role
 */
final class UserShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new User Show Controller.
     *
     * @param ListFieldsQuery  $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param UserIncludeQuery $includeQuery composes validated includes onto any User builder
     */
    public function __construct(
        private readonly ListFieldsQuery $fieldsQuery,
        private readonly UserIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the route-bound User with optional sparse fieldsets and includes.
     *
     * @param  UserShowRequest $request the validated show request
     * @param  User            $user    the route-bound User (reloaded with selected columns and includes)
     * @return JsonResponse    the standard API success envelope
     */
    public function __invoke(UserShowRequest $request, User $user): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

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

        /** @var User $loadedUser */
        $loadedUser = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new UserResource($loadedUser),
            message: 'User Retrieved Successfully',
        );
    }
}
