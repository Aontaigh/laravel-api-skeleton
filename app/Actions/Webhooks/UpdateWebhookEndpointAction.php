<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\DataTransferObjects\Webhooks\UpdateWebhookEndpointData;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Validation\ValidationException;

/**
 * Updates a webhook endpoint's configuration.
 */
final class UpdateWebhookEndpointAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdateWebhookEndpointAction.
     *
     * @param WebhookUrlGuard $urlGuard screens target URLs against SSRF
     */
    public function __construct(
        private readonly WebhookUrlGuard $urlGuard,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the validated changes and return the refreshed endpoint.
     *
     * Re-enabling a disabled endpoint clears its failure streak and disabled
     * marker: the operator has looked at it, so past failures must not
     * immediately re-disable it on the next delivery.
     *
     * @example
     * app(UpdateWebhookEndpointAction::class)->execute($data);
     *
     * @param  UpdateWebhookEndpointData $data the validated update payload
     * @return WebhookEndpoint           the refreshed endpoint
     *
     * @throws ValidationException when the new target URL fails the SSRF screen
     */
    public function execute(UpdateWebhookEndpointData $data): WebhookEndpoint
    {
        $attributes = [];

        if ($data->name !== null) {
            $attributes['name'] = $data->name;
        }

        if ($data->url !== null) {
            if (! $this->urlGuard->allows($data->url)) {
                throw ValidationException::withMessages([
                    'url' => ['URL Is Not Eligible For Webhook Delivery'],
                ]);
            }

            $attributes['url'] = $data->url;
        }

        if ($data->events !== null) {
            $attributes['events'] = array_values(array_unique($data->events));
        }

        if ($data->isActive !== null) {
            $attributes['is_active'] = $data->isActive;

            if ($data->isActive) {
                $attributes['failure_streak'] = 0;
                $attributes['disabled_at'] = null;
            }
        }

        $data->endpoint->update($attributes);

        return $data->endpoint->refresh();
    }
}
