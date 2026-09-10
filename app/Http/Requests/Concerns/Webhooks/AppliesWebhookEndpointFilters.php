<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Webhooks;

use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Queries\Webhooks\WebhookEndpointQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared webhook endpoint index filter rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesWebhookEndpointFilters
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;
    use ParsesSearchQueryParam;
    use ParsesSortQueryParam;

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
     * Validation rules for webhook endpoint index query params.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function webhookEndpointFilterRules(): array
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
                'max:'.WebhookEndpointQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->fieldsQueryParamRules('webhook_endpoints'),
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
    protected function validateWebhookEndpointFilterKeys(Validator $validator): void
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
        return ['search'];
    }

    /**
     * Get the columns callers may sort on via `?sort=`.
     *
     * @return list<string>
     */
    protected function allowedSortColumns(): array
    {
        return WebhookEndpointQueryConstraints::ALLOWED_SORTS;
    }

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
