<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\SearchTermParser;

/**
 * Parses the `filter[search]` query param into a normalised string.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ParsesSearchQueryParam
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
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

        return SearchTermParser::normalize(
            $this->safe()->string('filter.search')->toString(),
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
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
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
