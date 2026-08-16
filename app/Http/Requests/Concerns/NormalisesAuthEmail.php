<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\EmailAddress;

/**
 * Lowercases the email attribute before validation on FormRequests that accept
 * user email addresses.
 *
 * Composed via {@see PreparesPlainTextAndEmail} and {@see PreparesAuthCredentials}.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait NormalisesAuthEmail
{
    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Store the email address in lowercase before validation runs.
     */
    protected function normaliseAuthEmail(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => EmailAddress::normalise($this->string('email')->toString()),
            ]);
        }
    }
}
