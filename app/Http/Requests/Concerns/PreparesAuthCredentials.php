<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Normalises and sanitises shared auth credential attributes before validation.
 *
 * Composed by login and registration FormRequests. Declare plain-text keys via
 * {@see plainTextAttributeKeys()}.
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

    use NormalisesAuthEmail;
    use SanitisesPlainTextAttributes {
        prepareForValidation as private sanitisePlainTextPrepareForValidation;
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
        $this->normaliseAuthEmail();
    }
}
