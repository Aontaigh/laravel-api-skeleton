<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Validates and authorises an admin request to issue a Token for another User.
 */
final class StoreUserTokenRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ValidatesTokenPayload;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may issue Tokens for other Users
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $this->user()?->can('createForUser', PersonalAccessToken::class) === true
            && $user instanceof User
            && ! $user->isServiceAccount();
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> the create-Token validation rules
     */
    public function rules(): array
    {
        return $this->tokenPayloadRules();
    }
}
