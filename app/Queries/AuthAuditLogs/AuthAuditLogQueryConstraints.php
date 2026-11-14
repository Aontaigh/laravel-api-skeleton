<?php

declare(strict_types=1);

namespace App\Queries\AuthAuditLogs;

/**
 * Allow-lists shared by AuthAuditLogIndexRequest and the Auth Audit Log Query classes.
 */
final class AuthAuditLogQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * The `auth_audit_logs` table name, used to qualify columns so joins stay unambiguous.
     *
     * Qualifying columns with this constant keeps joins against related tables unambiguous and means a
     * future table rename only touches this line.
     *
     * @var string
     */
    public const TABLE = 'auth_audit_logs';

    /**
     * Columns callers may sort on via `?sort=`.
     *
     * Anything outside this list is rejected with `422` rather than reaching `orderBy`, so raw
     * input can never name a column. Extending the list is the only way a column becomes
     * sortable - add the column and the OpenAPI allow-list in the same change.
     *
     * @var list<string>
     */
    public const ALLOWED_SORTS = ['id', 'event', 'email', 'user_id', 'api_client_id', 'created_at'];

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
     * Sparse fieldset columns every viewer may request via `fields[auth_audit_logs]=`.
     *
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS = [
        'id',
        'user_id',
        'actor_user_id',
        'event',
        'outcome',
        'client_ineligibility_reason',
        'email',
        'ip_address',
        'user_agent',
        'location_city',
        'location_country',
        'personal_access_token_id',
        'api_client_id',
        'remember_me',
        'request_id',
        'created_at',
    ];

    /**
     * `fields[…]` keys the index accepts.
     *
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while
     * the primary key constrains the root table.
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS_KEYS = ['auth_audit_logs', 'users'];

    /**
     * Sort column applied when `sort` is omitted.
     *
     * The default must name a column in `ALLOWED_SORTS`, so the documented order and the enforced
     * allow-list cannot drift apart; an unsorted request still gets deterministic pagination.
     *
     * @var string
     */
    public const DEFAULT_SORT_COLUMN = 'id';

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
     * A comma-separated list becomes a `whereIn`, so an uncapped list would let one request
     * hand the database an arbitrarily long `IN` clause. Sized below the largest cap any
     * surveyed vendor publishes (Reddit Ads allows 200 IDs, Zendesk 100) and comfortably inside
     * a URL length budget for integer IDs.
     *
     * @var int
     */
    public const MAX_FILTER_USER_IDS = 50;

    /**
     * Largest list `filter[api_client_id]` may carry.
     *
     * A comma-separated list becomes a `whereIn`, so an uncapped list would hand the database an
     * arbitrarily long `IN` clause. The cap is tighter than the user-ID cap because client IDs are only
     * useful for targeted investigations.
     *
     * @var int
     */
    public const MAX_FILTER_CLIENT_IDS = 25;

    /**
     * Largest list `filter[event]` may carry.
     *
     * Tight because the allowed values are a closed enum of a couple of dozen members, so a
     * longer list can only be a mistake.
     *
     * @var int
     */
    public const MAX_FILTER_EVENTS = 10;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Columns that must always be selected for an Auth Audit Log query.
     *
     * @param  list<string> $includes validated include keys
     * @return list<string>
     */
    public static function requiredSelectColumns(array $includes): array
    {
        $required = ['id'];

        if (in_array('user', $includes, true)) {
            $required[] = 'user_id';
        }

        return $required;
    }
}
