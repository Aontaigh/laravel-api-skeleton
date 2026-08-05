<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Users\AppliesUserShowParams;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises User Show requests.
 */
final class UserShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesUserShowParams;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may view the route-bound User
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            && $this->user()?->can('view', $user) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the User Show validation rules
     */
    public function rules(): array
    {
        return $this->userShowRules();
    }

    /**
     * Run allow-list validation for include and fields params.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateUserShowParams($validator);
    }
}
