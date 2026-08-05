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

    /** The Spatie roles table name. */
    public const TABLE = 'roles';

    /**
     * Guard name stored on role rows.
     *
     * Spatie seeds roles against `auth.defaults.guard` at migration/seed time
     * (`web` in this application). Sanctum may switch the runtime default
     * guard during API requests, so the index must not derive the guard from
     * the current request — pin it here instead.
     */
    public const GUARD_NAME = 'web';

    /** @var list<string> columns callers may sort on via `?sort=` */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at'];

    /** @var list<string> relations callers may request via `?include=` */
    public const ALLOWED_INCLUDES = ['permissions'];

    /** @var list<string> columns callers may request via `fields[roles]=` */
    public const ALLOWED_FIELDS = ['id', 'name', 'created_at'];

    /** @var list<string> `fields[…]` keys accepted on the Role Index */
    public const ALLOWED_FIELDS_KEYS = ['roles', 'permissions'];

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
