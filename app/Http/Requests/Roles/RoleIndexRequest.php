<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Roles\AppliesRoleFilters;
use Illuminate\Contracts\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * Validates and authorises Role Index requests.
 */
final class RoleIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesRoleFilters;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list Roles
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Role::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the Role Index validation rules
     */
    public function rules(): array
    {
        return $this->roleFilterRules();
    }

    /**
     * Run allow-list validation for filter, sort, include, and fields params.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'roles');
        $this->validateFieldsQueryParam($validator, 'permissions');
        $this->validateSortQueryParam($validator);
        $this->validateIncludeQueryParam($validator);
    }
}
