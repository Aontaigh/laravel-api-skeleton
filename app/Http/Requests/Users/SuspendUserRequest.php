<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;

/**
 * Authorises a request to suspend a User.
 */
final class SuspendUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to suspend the route-bound User.
     *
     * @return bool true when the caller holds `users.suspend` and is not the target
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            && $this->user()?->can('suspend', $user) === true;
    }

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
