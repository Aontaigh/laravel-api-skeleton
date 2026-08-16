<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Sanitises plain-text fields and lowercases email before validation.
 *
 * Composed by registration, login, and admin user-creation FormRequests.
 * Declare plain-text keys via {@see plainTextAttributeKeys()}.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait PreparesPlainTextAndEmail
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
        $this->sanitisePlainTextPrepareForValidation();
        $this->normaliseAuthEmail();
    }
}
