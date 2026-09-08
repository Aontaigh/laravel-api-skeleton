<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for the public application info endpoint.
 *
 * Takes no input - the controller returns static application metadata.
 * The request class exists for signature consistency.
 */
final class ShowAppInfoRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; App Info is public metadata
     */
    public function authorize(): bool
    {
        return true;
    }

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
