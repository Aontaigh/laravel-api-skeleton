<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Tokens;

use App\Http\Requests\Concerns\ReadsRequestInput;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;

/**
 * Shared validation rules and typed accessors for issuing a Personal Access Token.
 *
 * Composed by the self-service `StoreTokenRequest`, the admin
 * `StoreUserTokenRequest`, and `UpdateClientRequest` so the payload shape
 * never drifts between them.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ValidatesTokenPayload
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;
    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the requested Token abilities, defaulting to unrestricted (`*`).
     *
     * @return list<string> the Token abilities
     */
    public function tokenAbilities(): array
    {
        return $this->optionalTokenAbilities() ?? ['*'];
    }

    /**
     * Get validated abilities when the client sent `abilities`, or null when omitted.
     *
     * @return list<string>|null the ability names, or null when the key is absent
     */
    public function optionalTokenAbilities(): ?array
    {
        if (! $this->safe()->has('abilities')) {
            return null;
        }

        return $this->validatedTokenAbilities();
    }
    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Build a typed ability list from the validated `abilities` array.
     *
     * @return list<string> the ability names
     */
    protected function validatedTokenAbilities(): array
    {
        /*
         * `abilities.*` is validated as `string`, but that only proves the
         * shape at the HTTP boundary — PHPStan still sees the validated
         * array as `array<mixed>`. Building the list element-by-element
         * behind an `is_string()` guard (rather than casting) lets
         * PHPStan narrow every entry pushed on, so the result is provably
         * `list<string>` instead of asserting it.
         */
        $abilities = [];

        foreach ($this->safe()->array('abilities') as $ability) {
            if (is_string($ability)) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the Token payload.
     *
     * @return array<string, array<int, string>> the Token payload rules
     */
    protected function tokenPayloadRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string'],
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
        return ['name'];
    }
}
