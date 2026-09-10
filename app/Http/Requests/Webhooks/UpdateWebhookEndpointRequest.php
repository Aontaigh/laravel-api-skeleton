<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Enums\WebhookEvent;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;
use App\Models\WebhookEndpoint;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Authorises and validates a request to update a webhook endpoint.
 */
final class UpdateWebhookEndpointRequest extends ApiFormRequest
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
     * @return array<string, array<int, mixed>> the update payload rules
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'max:2048', 'url'],
            'events' => ['sometimes', 'required', 'array', 'min:1', 'max:20'],
            'events.*' => ['string', Rule::in(WebhookEvent::values())],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Configure the validator instance.
     *
     * @param  Validator $validator the validator under construction
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasAny(['name', 'url', 'events', 'is_active'])) {
                return;
            }

            $validator->errors()->add(
                'name',
                'At Least One Field Is Required',
            );
        });
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
