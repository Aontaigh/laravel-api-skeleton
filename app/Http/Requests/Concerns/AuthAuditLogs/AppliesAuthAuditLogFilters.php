<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\AuthAuditLogs;

use App\Enums\AuthAuditEvent;
use App\Http\Requests\Concerns\AppliesDateRangeFilters;
use App\Http\Requests\Concerns\ParsesCommaListQueryParam;
use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use App\Support\AllowListValidation;
use App\Support\CommaListRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared auth audit log Index filter rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesAuthAuditLogFilters
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesDateRangeFilters;
    use ParsesCommaListQueryParam;
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
     * Get the requested `fields[auth_audit_logs]` columns, or null.
     *
     * @return list<string>|null
     */
    public function authAuditLogFields(): ?array
    {
        return $this->fieldsFor('auth_audit_logs');
    }

    /**
     * Get the requested `fields[users]` columns, or null.
     *
     * @return list<string>|null
     */
    public function auditLogUserFields(): ?array
    {
        return $this->fieldsFor('users');
    }

    /**
     * Get the validated audit event filters.
     *
     * Accepts one value or a comma-separated list, and returns an empty list
     * when absent so the caller branches on emptiness alone.
     *
     * @return list<AuthAuditEvent> the audit events to match, empty when unfiltered
     */
    public function eventFilters(): array
    {
        return array_map(
            AuthAuditEvent::from(...),
            $this->stringList('filter.event', AuthAuditLogQueryConstraints::MAX_FILTER_EVENTS),
        );
    }

    /**
     * Get the validated User filters.
     *
     * @return list<int> the User IDs to match, empty when unfiltered
     */
    public function userIdFilters(): array
    {
        return $this->integerList('filter.user_id', AuthAuditLogQueryConstraints::MAX_FILTER_USER_IDS);
    }

    /**
     * Get the validated API Client filters.
     *
     * @return list<int> the API Client IDs to match, empty when unfiltered
     */
    public function apiClientIdFilters(): array
    {
        return $this->integerList('filter.api_client_id', AuthAuditLogQueryConstraints::MAX_FILTER_CLIENT_IDS);
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Reject `filter[…]` keys outside the allow-list.
     *
     *
     * @return array<string, array<int, mixed>>
     */
    protected function authAuditLogFilterRules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'fields' => ['sometimes', 'array'],
            ...$this->searchFilterRules(),
            ...$this->dateRangeFilterRules('filter.from', 'filter.to'),
            'filter.event' => [
                'sometimes',
                'nullable',
                'string',
                CommaListRule::in(AuthAuditLogQueryConstraints::MAX_FILTER_EVENTS, self::allowedEventFilterValues()),
            ],
            ...$this->commaListFilterRules('filter.user_id', AuthAuditLogQueryConstraints::MAX_FILTER_USER_IDS),
            ...$this->commaListFilterRules('filter.api_client_id', AuthAuditLogQueryConstraints::MAX_FILTER_CLIENT_IDS),
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.AuthAuditLogQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('auth_audit_logs'),
            ...$this->fieldsQueryParamRules('users'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reject `filter[…]` keys outside the allow-list.
     *
     * @param  Validator $validator the validator under extension
     * @return void
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
     * Get the `filter[…]` keys this resource accepts.
     *
     * @return list<string>
     */
    protected function allowedFilterKeys(): array
    {
        return ['search', 'event', 'user_id', 'api_client_id', 'from', 'to'];
    }

    /**
     * The list-capable filters on this request, mapped to their maximum list size.
     *
     * @return array<string, int>
     */
    protected function commaListFilterDefinitions(): array
    {
        return [
            'filter.event' => AuthAuditLogQueryConstraints::MAX_FILTER_EVENTS,
            'filter.user_id' => AuthAuditLogQueryConstraints::MAX_FILTER_USER_IDS,
            'filter.api_client_id' => AuthAuditLogQueryConstraints::MAX_FILTER_CLIENT_IDS,
        ];
    }

    /**
     * Get the columns callers may sort on via `?sort=`.
     *
     * @return list<string>
     */
    protected function allowedSortColumns(): array
    {
        return AuthAuditLogQueryConstraints::ALLOWED_SORTS;
    }

    /**
     * Get the relations callers may request via `?include=`.
     *
     * @return list<string>
     */
    protected function allowedIncludeKeys(): array
    {
        return AuthAuditLogQueryConstraints::ALLOWED_INCLUDES;
    }

    /**
     * Get the `fields[…]` resource keys this resource accepts.
     *
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return AuthAuditLogQueryConstraints::ALLOWED_FIELDS_KEYS;
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
            'auth_audit_logs' => AuthAuditLogQueryConstraints::ALLOWED_FIELDS,
            'users' => $this->allowedNestedUserFields(),
            default => [],
        };
    }

    /**
     * Get the `users` columns exposed under `fields[users]`.
     *
     * @return list<string>
     */
    public function allowedNestedUserFields(): array
    {
        $fields = UserQueryConstraints::ALLOWED_FIELDS;

        if ($this->viewer()->can('users.view-email')) {
            $fields[] = 'email';
        }

        return $fields;
    }

    /**
     * Get every persisted `AuthAuditEvent` value.
     *
     * @return list<string>
     */
    private static function allowedEventFilterValues(): array
    {
        return array_map(
            static fn (AuthAuditEvent $event): string => $event->value,
            AuthAuditEvent::cases(),
        );
    }
}
