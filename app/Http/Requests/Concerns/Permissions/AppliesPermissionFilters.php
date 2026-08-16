<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Permissions;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\Permissions\PermissionQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared Permission Index filter rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesPermissionFilters
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;
    use ParsesIncludeQueryParam;
    use ParsesSearchQueryParam;
    use ParsesSortQueryParam;
    use ResolvesAuthenticatedViewer;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get sparse fieldset columns for Permissions, or null when omitted.
     *
     * @return list<string>|null whitelisted Permission column names
     */
    public function permissionFields(): ?array
    {
        return $this->fieldsFor('permissions');
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for Permission Index query params.
     *
     * @return array<string, array<int, mixed>> the Permission Index rules
     */
    protected function permissionFilterRules(): array
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
                'max:'.PermissionQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('permissions'),
        ];
    }

    /**
     * Reject filter keys outside the resource allow-list.
     *
     * @param  Validator $validator the validator under extension
     * @return void      adds an error for each unknown `filter[…]` key
     */
    protected function validateFilterKeys(Validator $validator): void
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

    /**
     * Filter keys callers may send via `filter[…]`.
     *
     * @return list<string> the allowed filter keys (without the `filter.` prefix)
     */
    protected function allowedFilterKeys(): array
    {
        return ['search'];
    }

    /**
     * Columns callers may sort on via `?sort=`.
     *
     * @return list<string> the allowed sort columns
     */
    protected function allowedSortColumns(): array
    {
        return PermissionQueryConstraints::ALLOWED_SORTS;
    }

    /**
     * Relations callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    protected function allowedIncludeKeys(): array
    {
        return [];
    }

    /**
     * Resource keys callers may use under `fields[…]`.
     *
     * @return list<string> the allowed `fields` keys
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return PermissionQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
     * Columns callers may request for a given `fields[…]` resource key.
     *
     * @param  string       $resourceKey the `fields[…]` key
     * @return list<string> whitelisted column names, empty for an unknown key
     */
    protected function allowedFieldsFor(string $resourceKey): array
    {
        return match ($resourceKey) {
            'permissions' => PermissionQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }
}
