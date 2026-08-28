<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Auth\ResolvesTwoFactorPending;

/**
 * Validates the two-factor status request.
 */
final class TwoFactorStatusRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ResolvesTwoFactorPending;

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
            'two_factor_token' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
