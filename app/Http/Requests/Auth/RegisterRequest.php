<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesAuthCredentials;
use Illuminate\Validation\Rules\Password;

/**
 * Validates and authorises self-service User registration.
 */
final class RegisterRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use PreparesAuthCredentials;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Registration is open to unauthenticated callers.
     *
     * @return bool always true
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
     * @return array<string, array<int, mixed>> the registration rules
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Get custom validation messages.
     *
     * @return array<string, string> the custom messages
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Invalid Credentials',
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | Sanitisation
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['name', 'device_name'];
    }
}
