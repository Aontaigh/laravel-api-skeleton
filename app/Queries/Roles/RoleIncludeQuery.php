<?php

declare(strict_types=1);

namespace App\Queries\Roles;

use App\Queries\IndexFieldsQuery;
use App\Queries\Permissions\PermissionQueryConstraints;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * Applies whitelisted eager loads to a Role query builder.
 */
final class RoleIncludeQuery
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Role include query.
     *
     * @param IndexFieldsQuery $fieldsQuery composes sparse fieldsets onto nested relation builders
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
     * Compose eager loads onto the query.
     *
     * @param Builder<Role>     $query            the Role query builder
     * @param list<string>      $includes         validated include keys
     * @param list<string>|null $permissionFields sparse Permission columns for nested relations
     *
     * @throws InvalidArgumentException when an allowed include has no eager-load mapping
     */
    public function apply(
        Builder $query,
        array $includes,
        ?array $permissionFields = null,
    ): void {
        $allowed = array_values(array_intersect(
            $includes,
            RoleQueryConstraints::ALLOWED_INCLUDES,
        ));

        if ($allowed === []) {
            return;
        }

        $with = [];

        foreach ($allowed as $include) {
            $with += match ($include) {
                'permissions' => ['permissions' => $this->permissionsConstraint($permissionFields)],
                default => throw new InvalidArgumentException("Unmapped Include: {$include}"),
            };
        }

        $query->with($with);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build a constrained eager-load for the `permissions` relation.
     *
     * @param list<string>|null $permissionFields sparse Permission columns from `fields[permissions]`
     * @return Closure(Relation<*, *, *>): void the constraint passed to `with()`
     */
    private function permissionsConstraint(?array $permissionFields): Closure
    {
        $fieldsQuery = $this->fieldsQuery;

        return static function (Relation $relation) use ($permissionFields, $fieldsQuery): void {
            $fieldsQuery->apply(
                query: $relation->getQuery(),
                requestedFields: $permissionFields,
                allowedFields: PermissionQueryConstraints::ALLOWED_FIELDS,
                table: PermissionQueryConstraints::TABLE,
            );
        };
    }
}
