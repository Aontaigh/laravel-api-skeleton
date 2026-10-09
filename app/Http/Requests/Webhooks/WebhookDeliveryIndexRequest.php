<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Webhooks\AppliesWebhookDeliveryFilters;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises webhook delivery index requests.
 */
final class WebhookDeliveryIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesWebhookDeliveryFilters;

    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Deliveries inherit the endpoint's visibility: no separate permission.
     *
     * @return bool true when the User may view the route-bound endpoint
     */
    public function authorize(): bool
    {
        /** @var WebhookEndpoint|null $endpoint */
        $endpoint = $this->route('webhook_endpoint');

        return $endpoint instanceof WebhookEndpoint
            && $this->user()?->can('view', $endpoint) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->webhookDeliveryFilterRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for the request's query params.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateWebhookDeliveryFilterKeys($validator);
        $this->validateCommaListFilterHints($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'webhook_deliveries');
        $this->validateSortQueryParam($validator);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Validation failure copy for this request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            $this->commaListFilterMessages(),
            $this->dateRangeFilterMessages('filter.from', 'filter.to'),
        );
    }
}
