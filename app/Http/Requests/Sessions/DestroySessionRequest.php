<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Http\Requests\ApiFormRequest;

/**
 * Authorises a request to revoke one registered web session.
 */
final class DestroySessionRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may revoke that web session
     */
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('web_session')) === true;
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
