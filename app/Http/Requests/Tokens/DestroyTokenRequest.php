<?php

declare(strict_types=1);

namespace App\Http\Requests\Tokens;

use App\Http\Requests\ApiFormRequest;

/**
 * Authorises a request to revoke one of the current User's own Tokens.
 */
final class DestroyTokenRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Route model binding resolves `{token}` before this runs, so
     * `route('token')` is already the bound `PersonalAccessToken`.
     *
     * @return bool true when the User may revoke that Token
     */
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('token')) === true;
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
