<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Webhooks;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Queries\Webhooks\WebhookEndpointQueryConstraints;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared webhook endpoint show query-param rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesWebhookEndpointShowParams
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the requested `fields[webhook_endpoints]` columns, or null.
     *
     * @return list<string>|null
     */
    public function webhookEndpointFields(): ?array
    {
        return $this->fieldsFor('webhook_endpoints');
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for webhook endpoint show query params.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function webhookEndpointShowRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            ...$this->fieldsQueryParamRules('webhook_endpoints'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reject field keys outside the resource allow-list.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    protected function validateWebhookEndpointShowParams(Validator $validator): void
    {
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'webhook_endpoints');
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
        return WebhookEndpointQueryConstraints::ALLOWED_FIELDS_KEYS;
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
            'webhook_endpoints' => WebhookEndpointQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }
}
