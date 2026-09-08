<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for the signed e-mail verification link.
 *
 * No body input: the `id`/`hash` are route parameters and the `signed`
 * middleware validates the signature. Kept as a dedicated FormRequest for
 * controller signature consistency (see `php-validation-responses`).
 */
final class VerifyEmailRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; the signed link is the authorisation
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> an empty rule set; verified via the signed route
     */
    public function rules(): array
    {
        return [];
    }
}
