<?php

declare(strict_types=1);

namespace App\Queries\Tokens;

/**
 * Allow-lists shared by TokenIndexRequest and the Token Query classes.
 *
 * Single source of truth for sort columns, sparse fieldsets, and pagination
 * bounds on the self-service Token Index. No `include` keys are advertised —
 * every row belongs to the authenticated User, so nested relations are redundant.
 */
final class TokenQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The Sanctum Personal Access Token table name. */
    public const TABLE = 'personal_access_tokens';

    /** @var list<string> columns callers may sort on via `?sort=` */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at', 'last_used_at', 'expires_at'];

    /** @var list<string> relations callers may request via `?include=` */
    public const ALLOWED_INCLUDES = [];

    /** @var list<string> columns callers may request via `fields[tokens]=` */
    public const ALLOWED_FIELDS = [
        'id',
        'name',
        'abilities',
        'last_used_at',
        'expires_at',
        'created_at',
    ];

    /** @var list<string> `fields[…]` keys accepted on the Token Index */
    public const ALLOWED_FIELDS_KEYS = ['tokens'];

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
     * Columns that must always be selected for a Token Index query.
     *
     * @return list<string> column names required on `personal_access_tokens`
     */
    public static function requiredSelectColumns(): array
    {
        return ['id'];
    }
}
