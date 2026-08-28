<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Users\AppliesUserShowParams;
use App\Models\User;
use App\Queries\Users\UserQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises the authenticated User profile request.
 */
final class MeShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesUserShowParams;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Any authenticated non-service User may read their own profile.
     *
     * @return bool true when the caller may view their profile via `GET /me`
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('viewMe', $user);
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Columns the caller may request via `fields[users]=` on their own profile.
     *
     * Always includes `email` — unlike the User index and show endpoints.
     *
     * @return list<string> the User columns available on `GET /me`
     */
    public function allowedUserFields(): array
    {
        return [...UserQueryConstraints::ALLOWED_FIELDS, 'email'];
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the profile show validation rules
     */
    public function rules(): array
    {
        return $this->userShowRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for include and fields params.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateUserShowParams($validator);
    }
}
