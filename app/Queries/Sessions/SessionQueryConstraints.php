<?php

declare(strict_types=1);

namespace App\Queries\Sessions;

/**
 * Allow-lists shared by SessionIndexRequest and the Session Query classes.
 */
final class SessionQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The web session registry table name. */
    public const TABLE = 'web_sessions';

    /** @var list<string> columns callers may sort on via `?sort=` */
    public const ALLOWED_SORTS = [
        'id',
        'user_id',
        'device_name',
        'ip_address',
        'remember_me',
        'last_activity_at',
        'created_at',
    ];

    /** @var list<string> relations callers may request via `?include=` */
    public const ALLOWED_INCLUDES = ['user'];

    /** @var list<string> columns callers may request via `fields[sessions]=` */
    public const ALLOWED_FIELDS = [
        'id',
        'user_id',
        'device_name',
        'ip_address',
        'user_agent',
        'remember_me',
        'last_activity_at',
        'created_at',
    ];

    /** @var list<string> computed Session Index fields (not database columns) */
    public const COMPUTED_FIELDS = ['is_current'];

    /** @var list<string> `fields[…]` keys accepted on the Session Index */
    public const ALLOWED_FIELDS_KEYS = ['sessions', 'users'];

    /** Default sort column when `sort` is omitted. */
    public const DEFAULT_SORT_COLUMN = 'last_activity_at';

    /** Default sort direction when `sort` is omitted. */
    public const DEFAULT_SORT_DIRECTION = 'desc';

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
     * Columns that must always be selected for a Session Index query.
     *
     * @return list<string> column names required on `web_sessions`
     */
    public static function requiredSelectColumns(): array
    {
        return ['id', 'session_id', 'user_id'];
    }
}
