<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\AuthAuditLogs;

use App\Enums\AuthAuditEvent;
use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

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
     * @return list<string>|null
     */
    public function authAuditLogFields(): ?array
    {
        return $this->fieldsFor('auth_audit_logs');
    }

    /**
     * @return list<string>|null
     */
    public function auditLogUserFields(): ?array
    {
        return $this->fieldsFor('users');
    }

    public function eventFilter(): ?AuthAuditEvent
    {
        if (! $this->safe()->filled('filter.event')) {
            return null;
        }

        return AuthAuditEvent::from($this->safe()->string('filter.event')->toString());
    }

    public function userIdFilter(): ?int
    {
        if (! $this->safe()->filled('filter.user_id')) {
            return null;
        }

        return $this->safe()->integer('filter.user_id');
    }

    public function apiClientIdFilter(): ?int
    {
        if (! $this->safe()->filled('filter.api_client_id')) {
            return null;
        }

        return $this->safe()->integer('filter.api_client_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function authAuditLogFilterRules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'fields' => ['sometimes', 'array'],
            ...$this->searchFilterRules(),
            'filter.event' => ['sometimes', 'nullable', 'string', Rule::in(self::allowedEventFilterValues())],
            'filter.user_id' => ['sometimes', 'nullable', 'integer'],
            'filter.api_client_id' => ['sometimes', 'nullable', 'integer'],
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
     * @return list<string>
     */
    protected function allowedFilterKeys(): array
    {
        return ['search', 'event', 'user_id', 'api_client_id'];
    }

    /**
     * @return list<string>
     */
    protected function allowedSortColumns(): array
    {
        return AuthAuditLogQueryConstraints::ALLOWED_SORTS;
    }

    /**
     * @return list<string>
     */
    protected function allowedIncludeKeys(): array
    {
        return AuthAuditLogQueryConstraints::ALLOWED_INCLUDES;
    }

    /**
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return AuthAuditLogQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
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
