<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Webhooks\AppliesWebhookEndpointShowParams;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises webhook endpoint show requests.
 */
final class WebhookEndpointShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesWebhookEndpointShowParams;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
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
        return $this->webhookEndpointShowRules();
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
        $this->validateWebhookEndpointShowParams($validator);
    }
}
