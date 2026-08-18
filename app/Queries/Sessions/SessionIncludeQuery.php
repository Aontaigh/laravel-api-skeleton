<?php

declare(strict_types=1);

namespace App\Queries\Sessions;

use App\Models\WebSession;
use App\Queries\IndexFieldsQuery;
use App\Queries\Users\UserQueryConstraints;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Applies whitelisted eager loads to a Web Session query builder.
 */
final class SessionIncludeQuery
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
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
     * @param Builder<WebSession> $query      the Web Session query builder
     * @param list<string>        $includes   validated include keys
     * @param list<string>|null   $userFields sparse User columns for nested relations
     *
     * @throws InvalidArgumentException when an allowed include has no eager-load mapping
     */
    public function apply(
        Builder $query,
        array $includes,
        ?array $userFields = null,
    ): void {
        $allowed = array_values(array_intersect(
            $includes,
            SessionQueryConstraints::ALLOWED_INCLUDES,
        ));

        if ($allowed === []) {
            return;
        }

        $with = [];

        foreach ($allowed as $include) {
            $with += match ($include) {
                'user' => ['user' => $this->userConstraint($userFields)],
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
     * @param list<string>|null $userFields sparse User columns from `fields[users]`
     * @return Closure(Relation<*, *, *>): void
     */
    private function userConstraint(?array $userFields): Closure
    {
        $fieldsQuery = $this->fieldsQuery;

        return static function (Relation $relation) use ($userFields, $fieldsQuery): void {
            $fieldsQuery->apply(
                query: $relation->getQuery(),
                requestedFields: $userFields,
                allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
                table: 'users',
                requiredColumns: ['id'],
            );
        };
    }
}
