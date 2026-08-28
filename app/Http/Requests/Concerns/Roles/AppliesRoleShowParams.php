<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Roles;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Queries\Permissions\PermissionQueryConstraints;
use App\Queries\Roles\RoleQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared Role Show query-param rules and typed accessors.
 *
 * Composes reusable parse traits for `include` and sparse `fields[…]` only —
 * no sort, filter, or pagination on a show endpoint.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesRoleShowParams
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
     * Get sparse fieldset columns for Roles, or null when omitted.
     *
     * @return list<string>|null whitelisted Role column names
     */
    public function roleFields(): ?array
    {
        return $this->fieldsFor('roles');
    }

    /**
     * Get sparse fieldset columns for nested Permissions, or null when omitted.
     *
     * @return list<string>|null whitelisted Permission column names
     */
    public function permissionFields(): ?array
    {
        return $this->fieldsFor('permissions');
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for Role Show query params.
     *
     * @return array<string, array<int, mixed>> the Role Show rules
     */
    protected function roleShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('roles'),
            ...$this->fieldsQueryParamRules('permissions'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Relations callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    protected function allowedIncludeKeys(): array
    {
        return RoleQueryConstraints::ALLOWED_INCLUDES;
    }

    /**
     * Resource keys callers may use under `fields[…]`.
     *
     * @return list<string> the allowed `fields` keys
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return RoleQueryConstraints::ALLOWED_FIELDS_KEYS;
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
            'roles' => RoleQueryConstraints::ALLOWED_FIELDS,
            'permissions' => PermissionQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for include and fields params.
     *
     * @param Validator $validator the validator under extension
     */
    protected function validateRoleShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'roles');
        $this->validateFieldsQueryParam($validator, 'permissions');
        $this->validateIncludeQueryParam($validator);
    }
}
