<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Http\Requests\ApiFormRequest;

/**
 * Authorises a request to revoke every web session except the caller's current browser.
 */
final class DestroyOtherSessionsRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Self-service only: the endpoint touches nobody's rows but the caller's,
     * so `sessions.revoke-own` is the gate. Service accounts hold no browser
     * sessions to prune.
     *
     * @return bool true when the User may revoke their own other sessions
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
