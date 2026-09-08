<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Sanitises plain-text fields and lowercases email before validation.
 *
 * Composed by registration, login, and admin user-creation FormRequests.
 * Declare plain-text keys via {@see plainTextAttributeKeys()} and phone keys
 * via {@see e164PhoneAttributeKeys()}.
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
    use NormalisesE164PhoneAttributes;
    use SanitisesPlainTextAttributes {
        prepareForValidation as private sanitisePlainTextPrepareForValidation;
    }

    /*
    |--------------------------------------------------------------------------
    | Preparation
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->sanitisePlainTextPrepareForValidation();
        $this->normaliseAuthEmail();
        $this->normaliseE164PhoneAttributes($this->e164PhoneAttributeKeys());
    }
}
