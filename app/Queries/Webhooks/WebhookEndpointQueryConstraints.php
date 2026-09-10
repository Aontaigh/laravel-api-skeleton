<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

/**
 * Allow-lists shared by WebhookEndpointIndexRequest and the endpoint Query classes.
 */
final class WebhookEndpointQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The `webhook_endpoints` table name, used to qualify columns so joins stay unambiguous. */
    public const TABLE = 'webhook_endpoints';

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
        'name',
        'created_at',
    ];

    /**
     * Sparse fieldset columns every viewer may request via `fields[webhook_endpoints]=`.
     *
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
    /**
     * Sparse fieldset columns every viewer may request via `fields[webhook_endpoints]=`.
     * Values are trimmed and intersected against this list before `select()`, so a hand-crafted
     * key can neither inject a column nor widen the payload beyond what the Resource serialises.
     */
    public const ALLOWED_FIELDS = [
        'id',
        'name',
        'url',
        'events',
        'is_active',
        'failure_streak',
        'disabled_at',
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
    public const ALLOWED_FIELDS_KEYS = ['webhook_endpoints'];

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
     * Columns that must always be selected for an endpoint index query.
     *
     * Only the key: endpoint responses never join, so no foreign keys are needed.
     *
     * @return list<string> column names required on `webhook_endpoints`
     */
    public static function requiredSelectColumns(): array
    {
        return ['id'];
    }
}
