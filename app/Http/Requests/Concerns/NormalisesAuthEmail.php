<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Lowercases the email attribute before validation on auth FormRequests.
 *
 * Composed via {@see PreparesAuthCredentials} on login and registration requests.
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
                'email' => strtolower($this->string('email')->toString()),
            ]);
        }
    }
}
