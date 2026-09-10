<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\DataTransferObjects\Webhooks\CreatedWebhookEndpointResult;
use App\DataTransferObjects\Webhooks\CreateWebhookEndpointData;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a webhook endpoint with a one-time plaintext signing secret.
 */
final class CreateWebhookEndpointAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreateWebhookEndpointAction.
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
     * Persist a webhook endpoint with a one-time plaintext secret.
     *
     * @example
     * $result = app(CreateWebhookEndpointAction::class)->execute($data);
     *
     * @param  CreateWebhookEndpointData    $data the validated endpoint payload
     * @return CreatedWebhookEndpointResult the persisted endpoint and plaintext secret
     *
     * @throws ValidationException when the target URL fails the SSRF screen
     */
    public function execute(CreateWebhookEndpointData $data): CreatedWebhookEndpointResult
    {
        if (! $this->urlGuard->allows($data->url)) {
            throw ValidationException::withMessages([
                'url' => ['URL Is Not Eligible For Webhook Delivery'],
            ]);
        }

        $plainSecret = Str::random(40);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::query()->create([
            'user_id' => $data->ownerId,
            'name' => $data->name,
            'url' => $data->url,
            'events' => array_values(array_unique($data->events)),
            'secret' => $plainSecret,
            'is_active' => true,
            'failure_streak' => 0,
            'disabled_at' => null,
        ]);

        return new CreatedWebhookEndpointResult($endpoint, $plainSecret);
    }
}
