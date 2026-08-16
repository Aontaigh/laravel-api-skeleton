<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesAuthCredentials;

/**
 * Validates and authorises password-based login.
 */
final class LoginRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use PreparesAuthCredentials;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Login is open to unauthenticated callers.
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
     * @return array<string, array<int, string>> the login rules
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['device_name'];
    }
}
