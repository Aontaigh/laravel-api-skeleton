<?php

declare(strict_types=1);

namespace Tests\Support\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;

/**
 * Minimal harness exposing search filter validation for unit tests.
 */
final class SearchQueryParamHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesSearchQueryParam;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the search filter rules
     */
    public function rules(): array
    {
        return $this->searchFilterRules();
    }
}
