<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Enums\WebhookEvent;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;
use App\Models\WebhookEndpoint;
use Illuminate\Validation\Rule;

/**
 * Validates and authorises webhook endpoint creation.
 */
final class StoreWebhookEndpointRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may create webhook endpoints
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', WebhookEndpoint::class) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * The target URL shape is validated here; reachability and SSRF safety
     * (https-only, no private ranges) are enforced by the Action, which can
     * resolve DNS and is unit-testable without HTTP.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url'],
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['string', Rule::in(WebhookEvent::values())],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sanitisation
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['name'];
    }
}
