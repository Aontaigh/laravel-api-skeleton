<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Users;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\Roles\RoleQueryConstraints;
use App\Queries\Teams\TeamQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared User Index filter rules and typed accessors.
 *
 * Composes reusable parse traits and User-specific filter validation and
 * permission surface (row scoping, sparse-fieldset visibility).
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesUserFilters
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
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the viewer may list Users across every Team.
     *
     * @return bool true when the viewer holds `users.list-all`
     */
    public function listsAllTeams(): bool
    {
        return $this->viewer()->can('users.list-all');
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Columns the viewer may request via `fields[users]=`.
     *
     * `email` is only ever added for a viewer holding `users.view-email` —
     * every other caller gets the base allow-list, so the field is never
     * exposed to a User Index request that has no business seeing it.
     *
     * @return list<string> the User columns available to this viewer
     */
    public function allowedUserFields(): array
    {
        $fields = UserQueryConstraints::ALLOWED_FIELDS;

        if ($this->viewer()->can('users.view-email')) {
            $fields[] = 'email';
        }

        return $fields;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get sparse fieldset columns for Users, or null when omitted.
     *
     * @return list<string>|null whitelisted User column names
     */
    public function userFields(): ?array
    {
        return $this->fieldsFor('users');
    }

    /**
     * Get sparse fieldset columns for nested Teams (e.g. `team` include), or null when omitted.
     *
     * @return list<string>|null whitelisted Team column names
     */
    public function teamFields(): ?array
    {
        return $this->fieldsFor('teams');
    }

    /**
     * Get sparse fieldset columns for nested Roles (e.g. `role` include), or null when omitted.
     *
     * @return list<string>|null whitelisted Role column names
     */
    public function roleFields(): ?array
    {
        return $this->fieldsFor('roles');
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for User Index Query Params.
     *
     * @return array<string, array<int, mixed>> the User Index rules
     */
    protected function userFilterRules(): array
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
                'max:'.UserQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('users'),
            ...$this->fieldsQueryParamRules('teams'),
            ...$this->fieldsQueryParamRules('roles'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

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
        return UserQueryConstraints::ALLOWED_SORTS;
    }

    /**
     * Relations callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    protected function allowedIncludeKeys(): array
    {
        return UserQueryConstraints::ALLOWED_INCLUDES;
    }

    /**
     * Resource keys callers may use under `fields[…]`.
     *
     * @return list<string> the allowed `fields` keys
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return UserQueryConstraints::ALLOWED_FIELDS_KEYS;
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
            'users' => $this->allowedUserFields(),
            'teams' => TeamQueryConstraints::ALLOWED_FIELDS,
            'roles' => RoleQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }
}
