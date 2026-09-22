<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for the served OpenAPI specification.
 *
 * Takes no input - the controller streams the YAML document from disk. The
 * request class exists for signature consistency, so the controller always
 * type-hints a FormRequest.
 */
final class ShowOpenApiSpecRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; Basic Auth is middleware when configured
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> an empty rule set; the endpoint takes no input
     */
    public function rules(): array
    {
        return [];
    }
}
