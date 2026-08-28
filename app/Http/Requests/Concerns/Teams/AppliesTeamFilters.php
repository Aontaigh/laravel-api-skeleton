<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Teams;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Queries\Teams\TeamQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared Team Index filter rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesTeamFilters
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;
    use ParsesSearchQueryParam;
    use ParsesSortQueryParam;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
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
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function teamFilterRules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'fields' => ['sometimes', 'array'],
            ...$this->searchFilterRules(),
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.TeamQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->fieldsQueryParamRules('teams'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    protected function validateTeamFilterKeys(Validator $validator): void
    {
        $validator->after(function (Validator $check): void {
            /** @var mixed $filter */
            $filter = $this->input('filter', []);

            if (! is_array($filter)) {
                return;
            }

            $unknown = array_diff(array_keys($filter), $this->allowedFilterKeys());

            if ($unknown !== []) {
                $this->recordAllowListHint('filter', $this->allowedFilterKeys());
            }

            foreach ($unknown as $key) {
                $check->errors()->add(
                    "filter.{$key}",
                    AllowListValidation::unsupportedMessage('Unsupported Filter', [$key], $this->allowedFilterKeys()),
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * @return list<string>
     */
    protected function allowedFilterKeys(): array
    {
        return ['search'];
    }

    /**
     * @return list<string>
     */
    protected function allowedSortColumns(): array
    {
        return TeamQueryConstraints::ALLOWED_SORTS;
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
