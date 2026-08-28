<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\MfaMethod;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Auth\ResolvesTwoFactorPending;
use Illuminate\Validation\Rule;

/**
 * Validates the two-factor channel request.
 */
final class SendTwoFactorRequest extends ApiFormRequest
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
     * Public endpoint — the pending challenge lives in the session, not here.
     *
     * @return bool always true
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Preparation
    |--------------------------------------------------------------------------
    */

    /**
     * Normalise the requested channel before validation.
     */
    protected function prepareForValidation(): void
    {
        $channel = $this->input('channel');

        if (is_string($channel)) {
            $this->merge(['channel' => mb_strtolower(trim($channel))]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validated delivery channel.
     *
     * @return MfaMethod the requested channel
     */
    public function channel(): MfaMethod
    {
        /** @var MfaMethod $channel */
        $channel = $this->safe()->enum('channel', MfaMethod::class);

        return $channel;
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
            'channel' => ['required', Rule::enum(MfaMethod::class)],
            'two_factor_token' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
