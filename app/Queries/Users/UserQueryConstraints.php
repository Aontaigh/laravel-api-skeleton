<?php

declare(strict_types=1);

namespace App\Queries\Users;

/**
 * Allow-lists shared by UserIndexRequest and the User Query classes.
 *
 * Single source of truth for sort columns, includes, sparse fieldsets, and
 * pagination bounds. `ALLOWED_FIELDS` deliberately excludes `email` -
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

    /**
     * Columns callers may sort on via `?sort=`, before per-viewer filtering.
     * `email` is deliberately absent: `AppliesUserFilters::allowedSortColumns()`
     * appends it only for viewers holding `users.view-email`, mirroring the
     * sparse-fieldset gate, so the column cannot leak its existence through
     * ordering to viewers who may not read it.
     */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at'];

    /**
     * Relations callers may request via `?include=`.
     * Unknown keys answer `422`, so the index can never be pushed into
     * eager-loading an unlisted relation.
     */
    public const ALLOWED_INCLUDES = ['team', 'role'];

    /**
     * Account statuses callers may filter on via `filter[status]=`.
     *
     * `deleted` selects the trashed-only scope, which is the only way to reach
     * a soft-deleted record - and therefore the data source for the restore
     * flow.
     */
    public const ALLOWED_STATUSES = ['active', 'suspended', 'deleted'];

    /**
     * Largest list `filter[status]` may carry.
     *
     * Tighter than the ID caps because the allow-list has only three members, so a longer list
     * can only be a mistake.
     */
    public const MAX_FILTER_STATUSES = 3;

    /**
     * Largest list `filter[role]` may carry.
     *
     * Bounded by the four Role names that exist.
     */
    public const MAX_FILTER_ROLES = 4;

    /**
     * Sparse fieldset columns every viewer may request via `fields[users]=`.
     * `email` is deliberately absent from the base list: it is appended only
     * for viewers holding `users.view-email`, so the column cannot leak to
     * viewers who may not read it.
     */
    public const ALLOWED_FIELDS = ['id', 'name', 'phone', 'created_at'];

    /**
     * `fields[…]` keys accepted on the User Index.
     * Nested keys let a caller constrain eager-loaded relations
     * (`fields[teams]=id,name`) while the primary key constrains `users`.
     */
    public const ALLOWED_FIELDS_KEYS = ['users', 'teams', 'roles'];

    /** Default sort column when `sort` is omitted. */
    public const DEFAULT_SORT_COLUMN = 'id';

    /** Default sort direction when `sort` is omitted. */
    public const DEFAULT_SORT_DIRECTION = 'asc';

    /** Default page size when `per_page` is omitted. */
    public const DEFAULT_PER_PAGE = 25;

    /**
     * Hard maximum for `per_page`, regardless of what the caller sends.
     * The cap bounds the worst-case page a single request can pull, so one
     * caller cannot turn the index into a table dump - larger values answer
     * `422` instead of a huge page.
     */
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
     * are deliberately absent - SQL can `ORDER BY` and `WHERE` on a column
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
