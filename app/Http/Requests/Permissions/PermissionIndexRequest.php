<?php

declare(strict_types=1);

namespace App\Http\Requests\Permissions;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Permissions\AppliesPermissionFilters;
use Illuminate\Contracts\Validation\Validator;
use Spatie\Permission\Models\Permission;

/**
 * Validates and authorises Permission Index requests.
 */
final class PermissionIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesPermissionFilters;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list Permissions
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Permission::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the Permission Index validation rules
     */
    public function rules(): array
    {
        return $this->permissionFilterRules();
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
        $this->validateIncludeQueryParam($validator);
        $this->validateFieldsQueryParam($validator, 'permissions');
        $this->validateSortQueryParam($validator);
    }
}
