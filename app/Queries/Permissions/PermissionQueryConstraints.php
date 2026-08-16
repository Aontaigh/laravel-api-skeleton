<?php

declare(strict_types=1);

namespace App\Queries\Permissions;

/**
 * Allow-lists for Permission sparse fieldsets and nested includes.
 */
final class PermissionQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The Spatie permissions table name. */
    public const TABLE = 'permissions';

    /** @var list<string> columns callers may sort on via `?sort=` */
    public const ALLOWED_SORTS = ['id', 'name'];

    /** @var list<string> columns callers may request via `fields[permissions]=` */
    public const ALLOWED_FIELDS = ['id', 'name'];

    /** @var list<string> `fields[…]` keys accepted on the Permission Index */
    public const ALLOWED_FIELDS_KEYS = ['permissions'];

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
     * Columns that must always be selected for a Permission Index query.
     *
     * @param  list<string> $includes validated include keys
     * @return list<string> column names required on `permissions`
     */
    public static function requiredSelectColumns(array $includes): array
    {
        return ['id'];
    }
}
