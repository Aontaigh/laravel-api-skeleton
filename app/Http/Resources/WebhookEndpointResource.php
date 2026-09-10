<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\WebhookEndpoint;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a webhook endpoint.
 *
 * The signing `secret` is never serialised here - not even redacted. It
 * leaves the API exactly once, in the create and rotate responses that carry
 * it as a sibling field beside this resource.
 *
 * @property-read WebhookEndpoint $resource
 */
final class WebhookEndpointResource extends JsonResource
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use SerialisesSparseAttributes;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Transform the endpoint into its API shape.
     *
     * Omits keys for columns that were not selected (sparse fieldsets on
     * `fields[webhook_endpoints]`).
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised endpoint
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenAttributeSelected(
                'id',
                fn (): int => $this->resource->id,
            ),
            'name' => $this->whenAttributeSelected(
                'name',
                fn (): string => $this->resource->name,
            ),
            'url' => $this->whenAttributeSelected(
                'url',
                fn (): string => $this->resource->url,
            ),
            'events' => $this->whenAttributeSelected(
                'events',
                fn (): array => $this->resource->events,
            ),
            'is_active' => $this->whenAttributeSelected(
                'is_active',
                fn (): bool => $this->resource->is_active,
            ),
            'failure_streak' => $this->whenAttributeSelected(
                'failure_streak',
                fn (): int => $this->resource->failure_streak,
            ),
            'disabled_at' => $this->whenAttributeSelected(
                'disabled_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->disabled_at),
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
        ];
    }
}
