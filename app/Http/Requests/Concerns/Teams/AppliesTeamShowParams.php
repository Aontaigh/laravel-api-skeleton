<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Teams;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Queries\Teams\TeamQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared Team Show query-param rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesTeamShowParams
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the requested `fields[teams]` columns, or null.
     *
     * @return list<string>|null
     */
    public function teamFields(): ?array
    {
        return $this->fieldsFor('teams');
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Reject include or field keys outside the allow-list.
     *
     *
     * @return array<string, array<int, mixed>>
     */
    protected function teamShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->fieldsQueryParamRules('teams'),
        ];
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
    protected function validateTeamShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'teams');
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Get the `fields[…]` resource keys this resource accepts.
     *
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return TeamQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
     * Get the field allow-list for the given resource key.
     *
     * @param  string       $resourceKey the `fields[…]` key being resolved
     * @return list<string>
     */
    protected function allowedFieldsFor(string $resourceKey): array
    {
        return match ($resourceKey) {
            'teams' => TeamQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }
}
