<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Webhooks\AppliesWebhookEndpointFilters;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises webhook endpoint index requests.
 */
final class WebhookEndpointIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesWebhookEndpointFilters;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list webhook endpoints
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WebhookEndpoint::class) === true;
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
        return $this->webhookEndpointFilterRules();
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
        $this->validateWebhookEndpointFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'webhook_endpoints');
        $this->validateSortQueryParam($validator);
    }
}
