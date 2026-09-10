<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Http\Requests\ApiFormRequest;
use App\Models\WebhookEndpoint;

/**
 * Authorises a request to send a test ping to a webhook endpoint.
 */
final class TestWebhookEndpointRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Pinging exercises delivery, not configuration, but it emits an outbound
     * request on the caller's behalf - updating callers may ping.
     *
     * @return bool true when the User may update the route-bound endpoint
     */
    public function authorize(): bool
    {
        /** @var WebhookEndpoint|null $endpoint */
        $endpoint = $this->route('webhook_endpoint');

        return $endpoint instanceof WebhookEndpoint
            && $this->user()?->can('update', $endpoint) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> no request body is accepted
     */
    public function rules(): array
    {
        return [];
    }
}
