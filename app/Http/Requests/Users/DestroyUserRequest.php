<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;

/**
 * Authorises a request to soft-delete a User.
 */
final class DestroyUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may delete the route-bound User
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            && $this->user()?->can('delete', $user) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> no request body is accepted
     */
    public function rules(): array
    {
        return [];
    }
}
