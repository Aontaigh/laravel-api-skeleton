<?php

declare(strict_types=1);

namespace App\Queries\Permissions;

/**
 * Allow-lists for Permission sparse fieldsets and nested includes.
 */
final class PermissionQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The Spatie permissions table name. */
    public const TABLE = 'permissions';

    /** @var list<string> columns callers may request via `fields[permissions]=` */
    public const ALLOWED_FIELDS = ['id', 'name'];
}
