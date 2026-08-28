<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\AllowListValidation;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

/**
 * Base FormRequest for API endpoints — failed validation returns the ApiResponse envelope.
 */
abstract class ApiFormRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var array<string, list<string>> */
    private array $allowListHints = [];

    /*
    |--------------------------------------------------------------------------
    | Validation Response
    |--------------------------------------------------------------------------
    */

    /**
     * Throw the standard API validation envelope instead of Laravel's default response.
     *
     * @param Validator $validator the failed validator instance
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                new ValidationException($validator),
                $this->allowListHints,
            ),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Hints
    |--------------------------------------------------------------------------
    */

    /**
     * Record supported values for an allow-list validation error.
     *
     * Merged into `meta.allowed` on `422` responses so callers can self-correct
     * without reading the OpenAPI spec.
     *
     * @param string       $errorKey the validation error key (e.g. `fields.users`, `sort`)
     * @param list<string> $values   the whitelisted values for that key
     */
    protected function recordAllowListHint(string $errorKey, array $values): void
    {
        $normalised = AllowListValidation::sorted($values);

        $this->allowListHints[$errorKey] = $normalised;
    }
}
