<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesAuthCredentials;
use App\Support\Auth\EmailMaxLength;
use App\Support\Auth\PasswordMaxLength;
use App\Support\Auth\PasswordResetTokenMaxLength;
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
     * The raw reset token is bounded to the broker's 64 characters before
     * hash comparison; the database column stores the hash, not the token.
     *
     * @return array<string, array<int, mixed>> the rules
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', PasswordResetTokenMaxLength::rule()],
            'email' => ['required', 'string', 'email', EmailMaxLength::rule()],
            'password' => ['bail', 'required', 'string', PasswordMaxLength::rule(), Password::defaults()],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.max' => PasswordResetTokenMaxLength::MESSAGE,
            'email.max' => EmailMaxLength::MESSAGE,
            'password.max' => PasswordMaxLength::MESSAGE,
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
