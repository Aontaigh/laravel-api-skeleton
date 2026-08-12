<?php

declare(strict_types=1);

namespace Tests\Support\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;

/**
 * Minimal harness exposing plain-text sanitisation for unit tests.
 */
final class PlainTextHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the validation rules
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
        ];
    }

    /**
     * The attribute keys to sanitise as plain text.
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['name'];
    }

    /**
     * Expose the protected sanitisation hook for testing.
     */
    public function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
    }
}
