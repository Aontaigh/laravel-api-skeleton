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

    /** The `web_sessions` table name, used to qualify columns so joins stay unambiguous. */
    public const TABLE = 'web_sessions';

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
    public const ALLOWED_SORTS = [
        'id',
        'user_id',
        'device_name',
        'ip_address',
        'remember_me',
        'last_activity_at',
        'created_at',
    ];

    /**
     * Relations callers may request via `?include=`.
     *
     * Unknown keys answer `422`, so the endpoint can never be pushed into eager-loading an unlisted relation.
    /**
     * Relations callers may request via `?include=`.
     * Unknown keys answer `422`, so the endpoint can never be pushed into eager-loading an
     * unlisted relation.
     */
    public const ALLOWED_INCLUDES = ['user'];

    /**
     * Sparse fieldset columns every viewer may request via `fields[web_sessions]=`.
     *
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted key can neither inject a column nor widen the payload beyond what the Resource serialises.
    /**
     * Sparse fieldset columns every viewer may request via `fields[web_sessions]=`.
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
     */
    public const ALLOWED_FIELDS = [
        'id',
        'user_id',
        'device_name',
        'ip_address',
        'user_agent',
        'location_city',
        'location_country',
        'remember_me',
        'last_activity_at',
        'created_at',
    ];

    /**
     * Sparse fieldset keys the Resource computes rather than reads from a column.
     *
     * These must stay out of the SQL `select()` list or the query fails on a column that does not exist; the Resource derives them per row instead.
    /**
     * Sparse fieldset keys the Resource computes rather than reads from a column.
     * These must stay out of the SQL `select()` list or the query fails on a column that does
     * not exist; the Resource derives them per row instead.
     */
    public const COMPUTED_FIELDS = ['is_current'];

    /**
     * `fields[…]` keys the index accepts.
     *
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while the primary key constrains the root table.
    /**
     * `fields[…]` keys the index accepts.
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while
     * the primary key constrains the root table.
     */
    public const ALLOWED_FIELDS_KEYS = ['sessions', 'users'];

    /** Sort column applied when `sort` is omitted. */
    public const DEFAULT_SORT_COLUMN = 'last_activity_at';

    /** Sort direction applied when `sort` is omitted. */
    public const DEFAULT_SORT_DIRECTION = 'desc';

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
     * Columns that must always be selected for a Session Index query.
     *
     * @return list<string> column names required on `web_sessions`
     */
    public static function requiredSelectColumns(): array
    {
        return ['id', 'session_id', 'user_id'];
    }
}
