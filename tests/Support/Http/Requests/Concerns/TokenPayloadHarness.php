<?php

declare(strict_types=1);

namespace Tests\Support\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;

/**
 * Minimal harness exposing token payload validation for unit tests.
 */
final class TokenPayloadHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ValidatesTokenPayload;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the token payload rules
     */
    public function rules(): array
    {
        return $this->tokenPayloadRules();
    }

    /**
     * Expose the protected sanitisation hook for testing.
     */
    public function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
    }

    /**
     * Expose the declared plain-text attribute keys for assertions.
     *
     * @return list<string> the attribute names to sanitise
     */
    public function exposedPlainTextAttributeKeys(): array
    {
        return $this->plainTextAttributeKeys();
    }
}
