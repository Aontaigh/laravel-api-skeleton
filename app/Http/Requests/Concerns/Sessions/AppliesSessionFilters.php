<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Sessions;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\Sessions\SessionQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared Web Session Index filter rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesSessionFilters
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
     * Whether the viewer may list web sessions across every User.
     *
     * @return bool true when the viewer holds `sessions.list-all`
     */
    public function listsAllUsers(): bool
    {
        return $this->viewer()->can('sessions.list-all');
    }

    /**
     * Get sparse fieldset columns for Sessions, or null when omitted.
     *
     * @return list<string>|null whitelisted Session column names
     */
    public function sessionFields(): ?array
    {
        return $this->fieldsFor('sessions');
    }

    /**
     * Get sparse fieldset columns for nested Users, or null when omitted.
     *
     * @return list<string>|null whitelisted User column names
     */
    public function sessionUserFields(): ?array
    {
        return $this->fieldsFor('users');
    }

    /**
     * Resolve the optional `filter[user_id]` value for admin viewers.
     */
    public function userIdFilter(): ?int
    {
        if (! $this->listsAllUsers() || ! $this->safe()->filled('filter.user_id')) {
            return null;
        }

        return $this->safe()->integer('filter.user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for Web Session Index query params.
     *
     * @return array<string, array<int, mixed>> the Session Index rules
     */
    protected function sessionFilterRules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.user_id' => ['sometimes', 'nullable', 'integer'],
            'fields' => ['sometimes', 'array'],
            ...$this->searchFilterRules(),
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.SessionQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('sessions'),
            ...$this->fieldsQueryParamRules('users'),
        ];
    }

    /**
     * Reject filter keys outside the resource allow-list.
     *
     * @param Validator $validator the validator under extension
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

            if (
                array_key_exists('user_id', $filter)
                && $filter['user_id'] !== null
                && ! $this->listsAllUsers()
            ) {
                $check->errors()->add(
                    'filter.user_id',
                    'The selected filter.user_id is not allowed for this caller.',
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
        return ['search', 'user_id'];
    }

    /**
     * Columns callers may sort on via `?sort=`.
     *
     * @return list<string> the allowed sort columns
     */
    protected function allowedSortColumns(): array
    {
        return SessionQueryConstraints::ALLOWED_SORTS;
    }

    /**
     * Relations callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    protected function allowedIncludeKeys(): array
    {
        return SessionQueryConstraints::ALLOWED_INCLUDES;
    }

    /**
     * Resource keys callers may use under `fields[…]`.
     *
     * @return list<string> the allowed `fields` keys
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return SessionQueryConstraints::ALLOWED_FIELDS_KEYS;
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
            'sessions' => $this->allowedSessionFields(),
            'users' => UserQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }

    /**
     * Session columns available to this viewer for sparse fieldsets.
     *
     * `user_id` is only exposed to callers who may list every User's sessions.
     * `is_current` is computed at serialisation time and is not a database column.
     *
     * @return list<string> the Session fields available to this viewer
     */
    public function allowedSessionFields(): array
    {
        return array_values(array_unique(array_merge(
            $this->allowedSessionDatabaseFields(),
            SessionQueryConstraints::COMPUTED_FIELDS,
        )));
    }

    /**
     * Session database columns available to this viewer for `fields[sessions]=`.
     *
     * @return list<string> the Session columns available to this viewer
     */
    public function allowedSessionDatabaseFields(): array
    {
        $fields = SessionQueryConstraints::ALLOWED_FIELDS;

        if (! $this->listsAllUsers()) {
            $fields = array_values(array_filter(
                $fields,
                static fn (string $field): bool => $field !== 'user_id',
            ));
        }

        return $fields;
    }
}
