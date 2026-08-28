<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Users\AppliesUserFilters;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises User Index requests.
 */
final class UserIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesUserFilters;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list Users
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the User Index validation rules
     */
    public function rules(): array
    {
        return $this->userFilterRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for filter, sort, include, and fields params.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateFieldsQueryParam($validator, 'teams');
        $this->validateFieldsQueryParam($validator, 'roles');
        $this->validateSortQueryParam($validator);
        $this->validateIncludeQueryParam($validator);
    }
}
