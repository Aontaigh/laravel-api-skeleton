<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PreparesPlainTextAndEmail;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates and authorises admin-initiated user creation.
 */
final class StoreUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use PreparesPlainTextAndEmail;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Only callers authorised to create users may do so.
     *
     * @return bool whether the caller may create a user
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'string', Rule::in(RoleName::Admin->value, RoleName::Manager->value, RoleName::User->value)],
            'team_id' => ['sometimes', 'integer', Rule::exists('teams', 'id')],
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
            'role.in' => 'The Selected Role Is Invalid',
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
        return ['name'];
    }
}
