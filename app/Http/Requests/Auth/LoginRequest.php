<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesAuthCredentials;
use App\Rules\PasswordByteLength;
use App\Support\Auth\EmailMaxLength;

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
    | Authorisation
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

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * `password` is a verification input, not a creation input, so the
     * configurable `PASSWORD_MAX_LENGTH` cap is deliberately absent: lowering
     * that config would otherwise lock out every account holding a longer
     * stored password, refusing to check a credential that is in fact valid.
     * `PasswordByteLength` supplies the fixed 72-byte hasher boundary, which
     * is what actually protects Argon2id from a CPU-exhaustion payload.
     *
     * @return array<string, array<int, mixed>> the login rules
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', EmailMaxLength::rule()],
            'password' => ['bail', 'required', 'string', new PasswordByteLength],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:255'],
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
            'email.max' => EmailMaxLength::MESSAGE,
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
        return ['device_name'];
    }
}
