<?php

declare(strict_types=1);

namespace App\Queries\ApiClients;

/**
 * Allow-lists shared by ClientIndexRequest and the ApiClient Query classes.
 */
final class ApiClientQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    public const TABLE = 'api_clients';

    /** @var list<string> */
    public const ALLOWED_SORTS = ['id', 'name', 'created_at', 'last_used_at'];

    /** @var list<string> */
    public const ALLOWED_FIELDS = ['id', 'name', 'client_id', 'abilities', 'is_active', 'last_used_at', 'created_at'];

    /** @var list<string> */
    public const ALLOWED_FIELDS_KEYS = ['api_clients'];

    public const DEFAULT_SORT_COLUMN = 'id';

    public const DEFAULT_SORT_DIRECTION = 'asc';

    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * @param  list<string> $includes validated include keys
     * @return list<string>
     */
    public static function requiredSelectColumns(array $includes): array
    {
        return ['id'];
    }
}
