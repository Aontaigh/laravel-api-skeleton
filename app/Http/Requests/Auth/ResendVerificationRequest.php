<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for resending the e-mail verification link.
 *
 * No input: the resend acts on the current authenticated User only, so it
 * cannot be used to enumerate or spam arbitrary addresses.
 */
final class ResendVerificationRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Deliberately no Policy delegation: there is no model to authorise
     * against - the endpoint is pure self-service on the authenticated User,
     * and the `auth:sanctum` middleware on the route is the gate.
     *
     * @return bool always true; authorisation is the authenticated group
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> an empty rule set; the endpoint takes no input
     */
    public function rules(): array
    {
        return [];
    }
}
