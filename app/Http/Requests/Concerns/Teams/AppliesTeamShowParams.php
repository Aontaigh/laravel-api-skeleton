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
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * @return list<string>|null
     */
    public function teamFields(): ?array
    {
        return $this->fieldsFor('teams');
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function teamShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->fieldsQueryParamRules('teams'),
        ];
    }

    protected function validateTeamShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'teams');
    }

    /**
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return TeamQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
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
