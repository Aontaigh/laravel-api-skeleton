<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;

/**
 * Authorises a request to unsuspend a User.
 */
final class UnsuspendUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to unsuspend the route-bound User.
     *
     * @return bool true when the caller holds `users.suspend`
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            && $this->user()?->can('unsuspend', $user) === true;
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
