<?php

declare(strict_types=1);

namespace App\Http\Requests\WellKnown;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for the public security.txt file.
 *
 * No input: the file path comes from server config, never the caller.
 */
final class ShowSecurityTxtRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; disclosure contact details are public by design
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> an empty rule set; nothing is accepted
     */
    public function rules(): array
    {
        return [];
    }
}
