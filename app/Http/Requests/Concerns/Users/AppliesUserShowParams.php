<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Users;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Queries\Roles\RoleQueryConstraints;
use App\Queries\Teams\TeamQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared User Show query-param rules and typed accessors.
 *
 * Composes reusable parse traits for `include` and sparse `fields[…]` only —
 * no sort, filter, or pagination on a show endpoint.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesUserShowParams
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
     * Get sparse fieldset columns for nested Teams, or null when omitted.
     *
     * @return list<string>|null whitelisted Team column names
     */
    public function teamFields(): ?array
    {
        return $this->fieldsFor('teams');
    }

    /**
     * Get sparse fieldset columns for nested Roles, or null when omitted.
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
     * Validation rules for User Show query params.
     *
     * @return array<string, array<int, mixed>> the User Show rules
     */
    protected function userShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('users'),
            ...$this->fieldsQueryParamRules('teams'),
            ...$this->fieldsQueryParamRules('roles'),
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
    protected function validateUserShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateFieldsQueryParam($validator, 'teams');
        $this->validateFieldsQueryParam($validator, 'roles');
        $this->validateIncludeQueryParam($validator);
    }
}
