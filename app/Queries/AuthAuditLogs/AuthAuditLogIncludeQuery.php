<?php

declare(strict_types=1);

namespace App\Queries\AuthAuditLogs;

use App\Models\AuthAuditLog;
use App\Queries\IndexFieldsQuery;
use App\Queries\Users\UserQueryConstraints;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Applies whitelisted eager loads to an Auth Audit Log query builder.
 */
final class AuthAuditLogIncludeQuery
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
     * @param Builder<AuthAuditLog> $query             the audit log query builder
     * @param list<string>          $includes          validated include keys
     * @param list<string>|null     $userFields        sparse User columns for nested relations
     * @param list<string>|null     $allowedUserFields permission-aware allow-list for the nested user select,
     *                                                 or null to fall back to the base set
     *
     * @throws InvalidArgumentException when an allowed include has no eager-load mapping
     */
    public function apply(
        Builder $query,
        array $includes,
        ?array $userFields = null,
        ?array $allowedUserFields = null,
    ): void {
        $allowed = array_values(array_intersect(
            $includes,
            AuthAuditLogQueryConstraints::ALLOWED_INCLUDES,
        ));

        if ($allowed === []) {
            return;
        }

        $with = [];

        foreach ($allowed as $include) {
            $with += match ($include) {
                'user' => ['user' => $this->userConstraint($userFields, $allowedUserFields)],
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
     * @param list<string>|null $userFields        sparse User columns from `fields[users]`
     * @param list<string>|null $allowedUserFields permission-aware allow-list, or null for the base set
     * @return Closure(Relation<*, *, *>): void
     */
    private function userConstraint(?array $userFields, ?array $allowedUserFields = null): Closure
    {
        $fieldsQuery = $this->fieldsQuery;
        $resolvedAllowedFields = $allowedUserFields ?? UserQueryConstraints::ALLOWED_FIELDS;

        return static function (Relation $relation) use ($userFields, $fieldsQuery, $resolvedAllowedFields): void {
            $fieldsQuery->apply(
                query: $relation->getQuery(),
                requestedFields: $userFields,
                allowedFields: $resolvedAllowedFields,
                table: 'users',
                requiredColumns: ['id'],
            );
        };
    }
}
