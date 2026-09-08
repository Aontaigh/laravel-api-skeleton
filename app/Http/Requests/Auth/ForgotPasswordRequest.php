<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesAuthCredentials;
use App\Support\Auth\EmailMaxLength;

/**
 * Validates the forgot-password request.
 */
final class ForgotPasswordRequest extends ApiFormRequest
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
     * Account recovery is open to unauthenticated callers.
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
     * @return array<string, array<int, string>> the rules
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', EmailMaxLength::rule()],
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
     * @return list<string> no plain-text fields; the email is normalised by the concern
     */
    protected function plainTextAttributeKeys(): array
    {
        return [];
    }
}
