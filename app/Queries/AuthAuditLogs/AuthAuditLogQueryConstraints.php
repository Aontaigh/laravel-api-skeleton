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

    public const TABLE = 'auth_audit_logs';

    /** @var list<string> */
    public const ALLOWED_SORTS = ['id', 'event', 'email', 'user_id', 'api_client_id', 'created_at'];

    /** @var list<string> */
    public const ALLOWED_INCLUDES = ['user'];

    /** @var list<string> */
    public const ALLOWED_FIELDS = [
        'id',
        'user_id',
        'event',
        'email',
        'ip_address',
        'user_agent',
        'personal_access_token_id',
        'api_client_id',
        'remember_me',
        'created_at',
    ];

    /** @var list<string> */
    public const ALLOWED_FIELDS_KEYS = ['auth_audit_logs', 'users'];

    public const DEFAULT_SORT_COLUMN = 'id';

    public const DEFAULT_SORT_DIRECTION = 'desc';

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
        $required = ['id'];

        if (in_array('user', $includes, true)) {
            $required[] = 'user_id';
        }

        return $required;
    }
}
