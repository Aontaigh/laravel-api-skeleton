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
     *
     * `email` is deliberately absent: `AppliesUserFilters::allowedSortColumns()`
     * appends it only for viewers holding `users.view-email`, mirroring the
     * sparse-fieldset gate, so the column cannot leak its existence through
     * ordering to viewers who may not read it.
     *
     * @var list<string>
     */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at'];

    /**
     * Relations callers may request via `?include=`.
     *
     * Unknown keys answer `422`, so the index can never be pushed into
     * eager-loading an unlisted relation.
     *
     * @var list<string>
     */
    public const ALLOWED_INCLUDES = ['team', 'role'];

    /**
     * Account statuses callers may filter on via `filter[status]=`.
     *
     * `deleted` selects the trashed-only scope, which is the only way to reach
     * a soft-deleted record - and therefore the data source for the restore
     * flow.
     *
     * @var list<string>
     */
    public const ALLOWED_STATUSES = ['active', 'suspended', 'deleted'];

    /**
     * Largest list `filter[status]` may carry.
     *
     * Tighter than the ID caps because the allow-list has only three members, so a longer list
     * can only be a mistake.
     *
     * @var int
     */
    public const MAX_FILTER_STATUSES = 3;

    /**
     * Largest list `filter[role]` may carry.
     *
     * Bounded by the four Role names that exist.
     *
     * @var int
     */
    public const MAX_FILTER_ROLES = 4;

    /**
     * Sparse fieldset columns every viewer may request via `fields[users]=`.
     *
     * `email` is deliberately absent from the base list: it is appended only
     * for viewers holding `users.view-email`, so the column cannot leak to
     * viewers who may not read it.
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS = ['id', 'name', 'phone', 'created_at'];

    /**
     * `fields[…]` keys accepted on the User Index.
     *
     * Nested keys let a caller constrain eager-loaded relations
     * (`fields[teams]=id,name`) while the primary key constrains `users`.
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS_KEYS = ['users', 'teams', 'roles'];

    /**
     * Sort column applied when `sort` is omitted.
     *
     * The primary key is present in every viewer's allow-list, including the per-viewer filtered set, so
     * the default cannot depend on a permission the caller may lack.
     *
     * @var string
     */
    public const DEFAULT_SORT_COLUMN = 'id';

    /**
     * Sort direction applied when `sort` is omitted.
     *
     * Ascending pairs with the default column so an unsorted request has a stable, documented order
     * instead of whatever order the database happens to return.
     *
     * @var string
     */
    public const DEFAULT_SORT_DIRECTION = 'asc';

    /**
     * Page size applied when `per_page` is omitted.
     *
     * The default keeps list responses a predictable size without forcing callers to send `per_page`; it
     * stays below `MAX_PER_PAGE`, so the hard cap only affects explicitly oversized requests.
     *
     * @var int
     */
    public const DEFAULT_PER_PAGE = 25;

    /**
     * Hard maximum for `per_page`, regardless of what the caller sends.
     *
     * The cap bounds the worst-case page a single request can pull, so one
     * caller cannot turn the index into a table dump - larger values answer
     * `422` instead of a huge page.
     *
     * @var int
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
