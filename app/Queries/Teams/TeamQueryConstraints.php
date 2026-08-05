<?php

declare(strict_types=1);

namespace App\Queries\Teams;

/**
 * Allow-lists for Team sparse fieldsets when nested under `include=team`.
 */
final class TeamQueryConstraints
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** @var list<string> columns callers may request via `fields[teams]=` */
    public const ALLOWED_FIELDS = ['id', 'name'];
}
