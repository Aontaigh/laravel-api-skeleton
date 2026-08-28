<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\AuthAuditLogs;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared auth audit log Show query-param rules and typed accessors.
 *
 * Composes reusable parse traits for `include` and sparse `fields[…]` only —
 * no sort, filter, or pagination on a show endpoint.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesAuthAuditLogShowParams
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;
    use ParsesIncludeQueryParam;
    use ResolvesAuthenticatedViewer;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
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
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function authAuditLogShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('auth_audit_logs'),
            ...$this->fieldsQueryParamRules('users'),
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

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    protected function validateAuthAuditLogShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'auth_audit_logs');
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateIncludeQueryParam($validator);
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

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
}
