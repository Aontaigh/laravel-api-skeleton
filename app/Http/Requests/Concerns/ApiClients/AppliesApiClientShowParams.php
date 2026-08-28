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
 * Composes reusable parse traits for sparse `fields[…]` only — no sort, filter,
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
     * @return list<string>
     */
    protected function allowedIncludeKeys(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return ApiClientQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
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

    protected function validateApiClientShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'api_clients');
        $this->validateIncludeQueryParam($validator);
    }
}
