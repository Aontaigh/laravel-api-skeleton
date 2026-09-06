<?php

declare(strict_types=1);

namespace App\Http\Requests\SystemHealth;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for retrieving the public system status.
 */
final class ShowSystemStatusRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; System Status is public, unauthenticated metadata
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The 90-day ceiling keeps the daily history bounded (one row per day)
     * and matches `DEFAULT_DAYS` on {@see \App\Http\Controllers\SystemHealth\SystemStatusController}.
     *
     * @return array<string, array<int, string>> the System Status validation rules
     */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ];
    }
}
