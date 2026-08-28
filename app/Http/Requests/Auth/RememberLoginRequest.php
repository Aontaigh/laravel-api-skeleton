<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;

/**
 * Authorises remember-me session restoration.
 */
final class RememberLoginRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Remember-me restoration is open to callers with a valid session or cookie.
     *
     * @return bool always true
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>> the validation rules
     */
    public function rules(): array
    {
        return [
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | Sanitisation
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['device_name'];
    }
}
