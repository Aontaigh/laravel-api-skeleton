<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\PlainText;

/**
 * Strips markup from configured plain-text request attributes before validation.
 *
 * Composed by any FormRequest that accepts user-facing display names or labels.
 * Declare the attribute keys via {@see plainTextAttributeKeys()}.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait SanitisesPlainTextAttributes
{
    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
    }

    /**
     * Strip markup from each configured plain-text attribute.
     */
    protected function sanitisePlainTextAttributes(): void
    {
        foreach ($this->plainTextAttributeKeys() as $key) {
            if (! $this->has($key) || ! is_string($this->input($key))) {
                continue;
            }

            $this->merge([
                $key => PlainText::sanitize($this->string($key)->toString()),
            ]);
        }
    }

    /**
     * List the request attribute keys that must be stored as plain text.
     *
     * @return list<string> the attribute names to sanitise
     */
    abstract protected function plainTextAttributeKeys(): array;
}
