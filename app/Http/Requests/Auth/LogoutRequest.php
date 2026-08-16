<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

/**
 * Authorises logout for the authenticated User.
 */
final class LogoutRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Only authenticated callers may log out.
     *
     * @return bool true when a User is authenticated
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Logout has no input fields.
     *
     * @return array<string, list<string>> the validation rules
     */
    public function rules(): array
    {
        return [];
    }
}
