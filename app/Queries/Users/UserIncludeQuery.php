<?php

declare(strict_types=1);

namespace App\Queries\Users;

use App\Models\User;
use App\Queries\IndexFieldsQuery;
use App\Queries\Roles\RoleQueryConstraints;
use App\Queries\Teams\TeamQueryConstraints;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Applies whitelisted eager loads to a User query builder.
 */
final class UserIncludeQuery
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new User include query.
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
     * `include=role` (singular, matching the other `fields[…]`-style query
     * param names) eager-loads the plural `roles` relationship Spatie
     * defines — this application models exactly one Role per User, so
     * `UserResource` collapses it back to a single `role` key.
     *
     * @param Builder<User>     $query      the User query builder
     * @param list<string>      $includes   validated include keys
     * @param list<string>|null $teamFields sparse Team columns for nested relations
     * @param list<string>|null $roleFields sparse Role columns for nested relations
     *
     * @throws InvalidArgumentException when an allowed include has no eager-load mapping
     */
    public function apply(
        Builder $query,
        array $includes,
        ?array $teamFields = null,
        ?array $roleFields = null,
    ): void {
        $allowed = array_values(array_intersect(
            $includes,
            UserQueryConstraints::ALLOWED_INCLUDES,
        ));

        if ($allowed === []) {
            return;
        }

        $with = [];

        /*
         * The default arm throws rather than skipping: adding a key to
         * ALLOWED_INCLUDES without an eager-load mapping here would
         * advertise an include that never loads. Omitting the arm entirely
         * would also fail loudly via \UnhandledMatchError, but PHPStan
         * cannot prove the match is exhaustive over `string`, so it
         * reports an error at level 8.
         */
        foreach ($allowed as $include) {
            $with += match ($include) {
                'team' => ['team' => $this->teamConstraint($teamFields)],
                'role' => ['roles' => $this->roleConstraint($roleFields)],
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
     * Build a constrained eager-load for the `team` relation.
     *
     * The closure takes a `Relation`, not a `Builder`. Eloquent invokes
     * eager-load constraints with the relation instance, which only
     * forwards to the builder through `__call` — typing the parameter as
     * `Builder` is a runtime `TypeError`, so the builder is unwrapped with
     * `getQuery()` before it is passed on.
     *
     * Left generic over `Relation`'s three template params (rather than
     * pinned to `Relation<Team, User, Team|null>`) because `Builder::with()`
     * itself accepts `Closure(Relation<*, *, *>): mixed` — a closure typed
     * to one concrete relation would not be assignable to that wildcard
     * shape when combined with `role`'s eager-load closure into one array.
     *
     * @param list<string>|null $teamFields sparse Team columns from `fields[teams]`
     * @return Closure(Relation<*, *, *>): void the constraint passed to `with()`
     */
    private function teamConstraint(?array $teamFields): Closure
    {
        $fieldsQuery = $this->fieldsQuery;

        return static function (Relation $relation) use ($teamFields, $fieldsQuery): void {
            $fieldsQuery->apply(
                query: $relation->getQuery(),
                requestedFields: $teamFields,
                allowedFields: TeamQueryConstraints::ALLOWED_FIELDS,
                table: 'teams',
            );
        };
    }

    /**
     * Build a constrained eager-load for the `roles` relation.
     *
     * @param list<string>|null $roleFields sparse Role columns from `fields[roles]`
     * @return Closure(Relation<*, *, *>): void the constraint passed to `with()`
     */
    private function roleConstraint(?array $roleFields): Closure
    {
        $fieldsQuery = $this->fieldsQuery;

        return static function (Relation $relation) use ($roleFields, $fieldsQuery): void {
            $fieldsQuery->apply(
                query: $relation->getQuery(),
                requestedFields: $roleFields,
                allowedFields: RoleQueryConstraints::ALLOWED_FIELDS,
                table: 'roles',
            );
        };
    }
}
