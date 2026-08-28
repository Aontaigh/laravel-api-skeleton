<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Auth\ResolvesTwoFactorPending;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;

/**
 * Validates the two-factor code submission.
 */
final class VerifyTwoFactorRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ResolvesTwoFactorPending;
    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Public endpoint — the pending challenge lives in the session or token.
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'two_factor_token' => ['sometimes', 'string', 'max:255'],
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
