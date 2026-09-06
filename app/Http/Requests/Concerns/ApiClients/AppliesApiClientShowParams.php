<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\ApiClients;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Queries\ApiClients\ApiClientQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared API client Show query-param rules and typed accessors.
 *
 * Composes reusable parse traits for sparse `fields[…]` only - no sort, filter,
 * pagination, or include on a show endpoint.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesApiClientShowParams
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;
    use ParsesIncludeQueryParam;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the requested `fields[api_clients]` columns, or null.
     *
     * @return list<string>|null
     */
    public function apiClientFields(): ?array
    {
        return $this->fieldsFor('api_clients');
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the API Client Show query-param validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function apiClientShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('api_clients'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Get the relations callers may request via `?include=`.
     *
     * @return list<string>
     */
    protected function allowedIncludeKeys(): array
    {
        return [];
    }

    /**
     * Get the `fields[…]` resource keys this resource accepts.
     *
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return ApiClientQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
     * Get the field allow-list for the given resource key.
     *
     *
     * @param  string       $resourceKey the `fields[…]` key being resolved
     * @return list<string>
     */
    protected function allowedFieldsFor(string $resourceKey): array
    {
        return match ($resourceKey) {
            'api_clients' => ApiClientQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reject include or field keys outside the allow-list.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    protected function validateApiClientShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'api_clients');
        $this->validateIncludeQueryParam($validator);
    }
}
