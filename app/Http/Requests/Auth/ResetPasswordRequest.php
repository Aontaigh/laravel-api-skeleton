<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesAuthCredentials;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the password reset request.
 *
 * Public endpoint: the reset token in the payload is the authorisation.
 */
final class ResetPasswordRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use PreparesAuthCredentials;

    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Password reset is open to unauthenticated callers.
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
     * The hashed reset token stored by the broker is 64 characters; the bound
     * is generous enough for alternate broker tables without accepting abuse.
     *
     * @return array<string, array<int, mixed>> the rules
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:255', Password::defaults()],
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
     * @return list<string> no plain-text fields; the email is normalised by the concern
     */
    protected function plainTextAttributeKeys(): array
    {
        return [];
    }
}
