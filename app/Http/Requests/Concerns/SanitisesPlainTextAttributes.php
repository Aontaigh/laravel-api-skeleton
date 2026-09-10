<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\PlainText;
use Illuminate\Support\Stringable;

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
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Abstract
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the request input contains a given key.
     *
     * Signatures here must match Laravel's exactly, or PHP rejects the host
     * class.
     *
     * @param  string|array<int, string> $key the input key, or a list of keys
     * @return bool                      whether any given key is present
     */
    abstract public function has($key);

    /**
     * Retrieve an input value wrapped in a Stringable accessor.
     *
     * @param  string     $key     the input key
     * @param  mixed      $default returned when the key is absent
     * @return Stringable the input value accessor
     */
    abstract public function string($key, $default = null);

    /**
     * Merge new input into the request's current input array.
     *
     * @param  array<string, mixed> $input the values to merge into the input
     * @return static               the request with the merged input
     */
    abstract public function merge(array $input);

    /*
    |--------------------------------------------------------------------------
    | Preparation
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
    }

    /*
    |--------------------------------------------------------------------------
    | Sanitisation
    |--------------------------------------------------------------------------
    */

    /**
     * Strip markup from each configured plain-text attribute.
     *
     * @return void
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
