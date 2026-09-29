<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;

/**
 * Authorises restoring a soft-deleted User record.
 *
 * The record is route-bound with `withTrashed()` so a trashed User resolves,
 * and the request class exists for signature consistency: it carries no rules.
 */
final class RestoreUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Authorise the request via the User Policy.
     *
     * @return bool whether the current User may restore the route-bound User
     */
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()?->can('restore', $target) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> an empty rule set; the User is route-bound
     */
    public function rules(): array
    {
        return [];
    }
}
