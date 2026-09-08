<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Sessions;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use App\Queries\Sessions\SessionQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared Web Session Show query-param rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesSessionShowParams
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

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for Web Session Show query params.
     *
     * @return array<string, array<int, mixed>> the Session Show rules
     */
    protected function sessionShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->includeQueryParamRules(),
            ...$this->fieldsQueryParamRules('sessions'),
            ...$this->fieldsQueryParamRules('users'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reject include and field keys outside the resource allow-lists.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    protected function validateSessionShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'sessions');
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateIncludeQueryParam($validator);
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Get the `fields[…]` resource keys this resource accepts.
     *
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return SessionQueryConstraints::ALLOWED_FIELDS_KEYS;
    }

    /**
     * Relation keys callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    protected function allowedIncludeKeys(): array
    {
        return SessionQueryConstraints::ALLOWED_INCLUDES;
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
            'sessions' => SessionQueryConstraints::ALLOWED_FIELDS,
            'users' => UserQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }
}
