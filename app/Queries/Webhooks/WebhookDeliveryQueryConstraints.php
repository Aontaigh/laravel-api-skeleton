<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

/**
 * Allow-lists shared by the delivery index request and Query classes.
 */
final class WebhookDeliveryQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The `webhook_deliveries` table name, used to qualify columns so joins stay unambiguous. */
    public const TABLE = 'webhook_deliveries';

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
        'event',
        'status',
        'created_at',
    ];

    /**
     * Sparse fieldset columns every viewer may request via `fields[webhook_deliveries]=`.
     *
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
    /**
     * Sparse fieldset columns every viewer may request via `fields[webhook_deliveries]=`.
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
     */
    public const ALLOWED_FIELDS = [
        'id',
        'uuid',
        'event',
        'status',
        'attempts',
        'last_status_code',
        'last_error',
        'delivered_at',
        'created_at',
    ];

    /**
     * `fields[…]` keys the index accepts.
     *
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while
     * the primary key constrains the root table.
    /**
     * `fields[…]` keys the index accepts.
     * Nested keys let a caller constrain eager-loaded relations (`fields[users]=id,name`) while
     * the primary key constrains the root table.
     */
    public const ALLOWED_FIELDS_KEYS = ['webhook_deliveries'];

    /** Sort column applied when `sort` is omitted. */
    public const DEFAULT_SORT_COLUMN = 'id';

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
     * Columns that must always be selected for a delivery index query.
     *
     * @return list<string> column names required on `webhook_deliveries`
     */
    public static function requiredSelectColumns(): array
    {
        return ['id'];
    }
}
