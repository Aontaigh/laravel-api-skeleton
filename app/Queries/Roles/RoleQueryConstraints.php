<?php

declare(strict_types=1);

namespace App\Queries\Roles;

/**
 * Allow-lists shared by RoleIndexRequest and the Role Query classes.
 *
 * Single source of truth for sort columns, includes, sparse fieldsets, and
 * pagination bounds on the Role Index and nested `role` includes on Users.
 */
final class RoleQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The `roles` table name, used to qualify columns so joins stay unambiguous. */
    public const TABLE = 'roles';

    /**
     * Guard name stored on role rows.
     *
     * Spatie seeds roles against `auth.defaults.guard` at migration/seed time
     * (`web` in this application). Sanctum may switch the runtime default
     * guard during API requests, so the index must not derive the guard from
     * the current request - pin it here instead.
    /**
     * Guard name stored on role rows.
     * Spatie seeds roles against `auth.defaults.guard` at migration/seed time
     * (`web` in this application). Sanctum may switch the runtime default
     * guard during API requests, so the index must not derive the guard from
     * the current request - pin it here instead.
     */
    public const GUARD_NAME = 'web';

    /**
     * Columns callers may sort on via `?sort=`.
     *
     * Anything outside this list is rejected with `422` rather than reaching `orderBy`, so raw input can never name a column. Extending the list is the only way a column becomes sortable - add the column and the OpenAPI allow-list in the same change.
    /**
     * Columns callers may sort on via `?sort=`.
     * Anything outside this list is rejected with `422` rather than reaching `orderBy`, so raw
     * input can never name a column. Extending the list is the only way a column becomes
     * sortable - add the column and the OpenAPI allow-list in the same change.
     */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at'];

    /**
     * Relations callers may request via `?include=`.
     *
     * Unknown keys answer `422`, so the endpoint can never be pushed into eager-loading an unlisted relation.
    /**
     * Relations callers may request via `?include=`.
     * Unknown keys answer `422`, so the endpoint can never be pushed into eager-loading an
     * unlisted relation.
     */
    public const ALLOWED_INCLUDES = ['permissions'];

    /**
     * Sparse fieldset columns every viewer may request via `fields[roles]=`.
     *
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted key can neither inject a column nor widen the payload beyond what the Resource serialises.
    /**
     * Sparse fieldset columns every viewer may request via `fields[roles]=`.
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
     */
    public const ALLOWED_FIELDS = ['id', 'name', 'created_at'];

    /**
     * `fields[…]` keys the index accepts.
     *
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while the primary key constrains the root table.
    /**
     * `fields[…]` keys the index accepts.
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while
     * the primary key constrains the root table.
     */
    public const ALLOWED_FIELDS_KEYS = ['roles', 'permissions'];

    /** Sort column applied when `sort` is omitted. */
    public const DEFAULT_SORT_COLUMN = 'id';

    /** Sort direction applied when `sort` is omitted. */
    public const DEFAULT_SORT_DIRECTION = 'asc';

    /** Page size applied when `per_page` is omitted. */
    public const DEFAULT_PER_PAGE = 25;

    /**
     * Hard maximum for `per_page`, regardless of what the caller sends.
     *
     * The cap bounds the worst-case page a single request can pull, so one caller cannot turn an index into a table dump - larger values answer `422` instead of a huge page.
    /**
     * Hard maximum for `per_page`, regardless of what the caller sends.
     * The cap bounds the worst-case page a single request can pull, so one caller cannot turn an
     * index into a table dump - larger values answer `422` instead of a huge page.
     */
    public const MAX_PER_PAGE = 100;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Columns that must always be selected for a Role Index query.
     *
     * @param  list<string> $includes validated include keys
     * @return list<string> column names required on `roles`
     */
    public static function requiredSelectColumns(array $includes): array
    {
        return ['id'];
    }
}
