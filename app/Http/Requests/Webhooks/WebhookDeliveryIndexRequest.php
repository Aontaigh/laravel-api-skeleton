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
    | Authorization
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
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'webhook_deliveries');
        $this->validateSortQueryParam($validator);
    }
}
