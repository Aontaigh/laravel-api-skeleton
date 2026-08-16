<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorises client-credentials token exchange.
 */
final class ClientTokenExchangeRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Token exchange is open to unauthenticated callers.
     *
     * @return bool always true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grant_type' => ['required', 'string', Rule::in(['client_credentials'])],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
        ];
    }
}
