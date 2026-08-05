<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Parses the `filter[search]` query param into a normalised string.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ParsesSearchQueryParam
{
    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the search term, or null when omitted.
     *
     * @return string|null the trimmed search term
     */
    public function searchTerm(): ?string
    {
        if (! $this->safe()->filled('filter.search')) {
            return null;
        }

        $term = trim($this->safe()->string('filter.search')->toString());

        return $term === '' ? null : $term;
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the search filter.
     *
     * @return array<string, array<int, string>> the search filter rules
     */
    protected function searchFilterRules(): array
    {
        return [
            'filter.search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
