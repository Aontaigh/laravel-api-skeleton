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

    /**
     * The `web_sessions` table name, used to qualify columns so joins stay unambiguous.
     *
     * Qualifying columns with this constant keeps joins against related tables unambiguous and means a
     * future table rename only touches this line.
     *
     * @var string
     */
    public const TABLE = 'web_sessions';

    /**
     * Columns callers may sort on via `?sort=`.
     *
     * Anything outside this list is rejected with `422` rather than reaching `orderBy`, so raw
     * input can never name a column. Extending the list is the only way a column becomes
     * sortable - add the column and the OpenAPI allow-list in the same change.
     *
     * @var list<string>
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
     * Unknown keys answer `422`, so the endpoint can never be pushed into eager-loading an
     * unlisted relation.
     *
     * @var list<string>
     */
    public const ALLOWED_INCLUDES = ['user'];

    /**
     * Sparse fieldset columns every viewer may request via `fields[web_sessions]=`.
     *
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
     *
     * @var list<string>
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
     * These must stay out of the SQL `select()` list or the query fails on a column that does
     * not exist; the Resource derives them per row instead.
     *
     * @var list<string>
     */
    public const COMPUTED_FIELDS = ['is_current'];

    /**
     * `fields[…]` keys the index accepts.
     *
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while
     * the primary key constrains the root table.
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS_KEYS = ['sessions', 'users'];

    /**
     * Sort column applied when `sort` is omitted.
     *
     * The default must name a column in `ALLOWED_SORTS`, so the documented order and the enforced
     * allow-list cannot drift apart; an unsorted request still gets deterministic pagination.
     *
     * @var string
     */
    public const DEFAULT_SORT_COLUMN = 'last_activity_at';

    /**
     * Sort direction applied when `sort` is omitted.
     *
     * Descending keeps the newest rows first, which is what an operator scanning a chronological list
     * expects before any filter is applied.
     *
     * @var string
     */
    public const DEFAULT_SORT_DIRECTION = 'desc';

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
     * The cap bounds the worst-case page a single request can pull, so one caller cannot turn an
     * index into a table dump - larger values answer `422` instead of a huge page.
     *
     * @var int
     */
    public const MAX_PER_PAGE = 100;

    /**
     * Largest list `filter[user_id]` may carry.
     *
     * Uncapped lists would let one request hand the database an arbitrarily long `IN` clause.
     *
     * @var int
     */
    public const MAX_FILTER_USER_IDS = 50;

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
