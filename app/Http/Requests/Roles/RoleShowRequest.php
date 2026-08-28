<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Roles\AppliesRoleShowParams;
use Illuminate\Contracts\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * Validates and authorises Role Show requests.
 */
final class RoleShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesRoleShowParams;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may view the route-bound Role
     */
    public function authorize(): bool
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return $role instanceof Role
            && $this->user()?->can('view', $role) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the Role Show validation rules
     */
    public function rules(): array
    {
        return $this->roleShowRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for include and fields params.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateRoleShowParams($validator);
    }
}
