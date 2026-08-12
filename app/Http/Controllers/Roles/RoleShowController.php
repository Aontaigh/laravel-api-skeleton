<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\Http\Requests\Roles\RoleShowRequest;
use App\Http\Resources\RoleResource;
use App\Queries\IndexFieldsQuery;
use App\Queries\Roles\RoleIncludeQuery;
use App\Queries\Roles\RoleQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Returns a single Role by ID.
 *
 * @example
 * GET /api/roles/{role}?fields[roles]=id,name&include=permissions&fields[permissions]=id,name
 */
final class RoleShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Role Show Controller.
     *
     * @param IndexFieldsQuery $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param RoleIncludeQuery $includeQuery composes validated includes onto any Role builder
     */
    public function __construct(
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly RoleIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the route-bound Role with optional sparse fieldsets and includes.
     *
     * @param  RoleShowRequest $request the validated show request
     * @param  Role            $role    the route-bound Role (reloaded with selected columns and includes)
     * @return JsonResponse    the standard API success envelope
     */
    public function __invoke(RoleShowRequest $request, Role $role): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $includes = $request->includes();
        $roleFields = $request->roleFields();
        $permissionFields = $request->permissionFields();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<Role> $query */
        $query = Role::query()
            ->whereKey($role->getKey())
            ->where(RoleQueryConstraints::TABLE.'.guard_name', RoleQueryConstraints::GUARD_NAME);

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $roleFields,
            allowedFields: RoleQueryConstraints::ALLOWED_FIELDS,
            table: RoleQueryConstraints::TABLE,
            requiredColumns: RoleQueryConstraints::requiredSelectColumns($includes),
        );

        $this->includeQuery->apply($query, $includes, $permissionFields);

        /** @var Role $loadedRole */
        $loadedRole = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new RoleResource($loadedRole),
            message: 'Role Retrieved Successfully',
        );
    }
}
