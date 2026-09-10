<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use App\Queries\Webhooks\WebhookDeliveryQueryConstraints;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared webhook delivery index filter rules and typed accessors.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesWebhookDeliveryFilters
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ParsesFieldsQueryParam;
    use ParsesSortQueryParam;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the requested `fields[webhook_deliveries]` columns, or null.
     *
     * @return list<string>|null
     */
    public function webhookDeliveryFields(): ?array
    {
        return $this->fieldsFor('webhook_deliveries');
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for webhook delivery index query params.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function webhookDeliveryFilterRules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.event' => ['sometimes', 'string', Rule::in([...WebhookEvent::values(), WebhookEvent::PING])],
            'filter.status' => ['sometimes', 'string', Rule::in(WebhookDeliveryStatus::values())],
            'fields' => ['sometimes', 'array'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.WebhookDeliveryQueryConstraints::MAX_PER_PAGE,
            ],
            ...$this->sortQueryParamRules(),
            ...$this->fieldsQueryParamRules('webhook_deliveries'),
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
    protected function validateWebhookDeliveryFilterKeys(Validator $validator): void
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
        return ['event', 'status'];
    }

    /**
     * Get the columns callers may sort on via `?sort=`.
     *
     * @return list<string>
     */
    protected function allowedSortColumns(): array
    {
        return WebhookDeliveryQueryConstraints::ALLOWED_SORTS;
    }

    /**
     * Get the `fields[…]` resource keys this resource accepts.
     *
     * @return list<string>
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return WebhookDeliveryQueryConstraints::ALLOWED_FIELDS_KEYS;
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
            'webhook_deliveries' => WebhookDeliveryQueryConstraints::ALLOWED_FIELDS,
            default => [],
        };
    }
}
