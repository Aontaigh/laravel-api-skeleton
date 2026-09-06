<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use App\Support\Auth\PasswordMaxLength;
use Illuminate\Validation\Rules\Password;

/**
 * Authorises and validates a self-service password change request.
 */
final class UpdateMePasswordRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Any interactive authenticated User may change their own password.
     *
     * @return bool true when the caller may change their password via `PATCH /me/password`
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('updateMe', $user);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * The new password enforces the app-wide `Password::defaults()` policy
     * (min 12, letters, mixed case, numbers, HaveIBeenPwned) so a User cannot
     * weaken their own password below the registration contract, and must
     * differ from the current one so a "change" is always a change.
     *
     * Password fields carry `PasswordMaxLength::rule()` so oversize input is
     * rejected before hash verification and cannot be abused for CPU
     * exhaustion against the Argon2id check.
     *
     * @example
     * (new UpdateMePasswordRequest)->rules()
     *
     * @return array<string, array<int, mixed>> the password change rules
     */
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', PasswordMaxLength::rule()],
            'password' => ['bail', 'required', 'string', PasswordMaxLength::rule(), 'confirmed', 'different:current_password', Password::defaults()],
            'password_confirmation' => ['required', 'string', PasswordMaxLength::rule()],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Get the custom validation messages.
     *
     * Full Title-Case strings so Laravel never interpolates a humanised
     * `:attribute` (API clients map `meta.errors` keys to the JSON they sent).
     *
     * @return array<string, string> the Title-Case overrides
     */
    public function messages(): array
    {
        return [
            'current_password.max' => PasswordMaxLength::MESSAGE,
            'password.confirmed' => 'Passwords Do Not Match',
            'password.different' => 'New Password Must Differ From Current Password',
            'password.max' => PasswordMaxLength::MESSAGE,
            'password_confirmation.max' => PasswordMaxLength::MESSAGE,
        ];
    }
}
