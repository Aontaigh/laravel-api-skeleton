<?php

declare(strict_types=1);

namespace App\Queries\Users;

/**
 * Allow-lists shared by UserIndexRequest and the User Query classes.
 *
 * Single source of truth for sort columns, includes, sparse fieldsets, and
 * pagination bounds. `ALLOWED_FIELDS` deliberately excludes `email` —
 * `AppliesUserFilters::allowedUserFields()` adds it only for viewers with
 * the `users.view-email` permission.
 */
final class UserQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** @var list<string> columns callers may sort on via `?sort=` */
    public const ALLOWED_SORTS = ['id', 'name', 'email', 'created_at'];

    /** @var list<string> relations callers may request via `?include=` */
    public const ALLOWED_INCLUDES = ['team', 'role'];

    /** @var list<string> columns every viewer may request via `fields[users]=` */
    public const ALLOWED_FIELDS = ['id', 'name', 'created_at'];

    /** @var list<string> `fields[…]` keys accepted on the User Index */
    public const ALLOWED_FIELDS_KEYS = ['users', 'teams', 'roles'];

    /** Default sort column when `sort` is omitted. */
    public const DEFAULT_SORT_COLUMN = 'id';

    /** Default sort direction when `sort` is omitted. */
    public const DEFAULT_SORT_DIRECTION = 'asc';

    /** Default page size when `per_page` is omitted. */
    public const DEFAULT_PER_PAGE = 25;

    /** Hard maximum for `per_page` to prevent abuse. */
    public const MAX_PER_PAGE = 100;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Columns that must always be selected for a User Index query.
     *
     * Only columns Eloquent needs in memory belong here: the key, plus the
     * foreign key behind each requested include. Sort and filter columns
     * are deliberately absent — SQL can `ORDER BY` and `WHERE` on a column
     * that is not in the select list, and adding them would push columns
     * the client did not ask for back into the response.
     *
     * @param  list<string> $includes validated include keys
     * @return list<string> column names required on `users` (without table prefix)
     */
    public static function requiredSelectColumns(array $includes): array
    {
        $required = ['id'];

        if (in_array('team', $includes, true)) {
            $required[] = 'team_id';
        }

        return $required;
    }
}
