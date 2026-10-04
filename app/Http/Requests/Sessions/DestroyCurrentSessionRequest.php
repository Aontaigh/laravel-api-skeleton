<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Http\Requests\ApiFormRequest;

/**
 * Authorises a request to revoke the caller's current browser session.
 *
 * The lookup lives in `CurrentWebSessionQuery` so the current-session
 * predicate (owner + session ID + not revoked) is uniform, and
 * `CurrentWebSessionQuery` owns the row lookup.
 */
final class DestroyCurrentSessionRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may revoke their own current session
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('sessions.revoke-own')
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
     * @return array<string, array<int, string>> no request body is accepted
     */
    public function rules(): array
    {
        return [];
    }
}
