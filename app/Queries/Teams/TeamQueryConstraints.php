<?php

declare(strict_types=1);

namespace App\Queries\Teams;

/**
 * Allow-lists for Team index, show, and nested `team` includes.
 */
final class TeamQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The teams table name. */
    public const TABLE = 'teams';

    /** @var list<string> columns callers may sort on via `?sort=` */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at'];

    /** @var list<string> columns callers may request via `fields[teams]=` */
    public const ALLOWED_FIELDS = ['id', 'name'];

    /** @var list<string> `fields[…]` keys accepted on the Team Index */
    public const ALLOWED_FIELDS_KEYS = ['teams'];

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
     * Columns that must always be selected for a Team Index query.
     *
     * @return list<string> column names required on `teams`
     */
    public static function requiredSelectColumns(): array
    {
        return ['id'];
    }
}
