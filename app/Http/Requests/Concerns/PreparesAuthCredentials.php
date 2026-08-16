<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Normalises and sanitises shared auth credential attributes before validation.
 *
 * Composed by login and registration FormRequests.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait PreparesAuthCredentials
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use PreparesPlainTextAndEmail;
}
