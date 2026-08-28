<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;

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
     * @return array<string, array<int, mixed>> the password change rules
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
