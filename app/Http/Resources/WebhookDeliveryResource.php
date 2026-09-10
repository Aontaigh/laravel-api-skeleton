<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\WebhookDelivery;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a webhook delivery attempt record.
 *
 * @property-read WebhookDelivery $resource
 */
final class WebhookDeliveryResource extends JsonResource
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
     * Transform the delivery into its API shape.
     *
     * Omits keys for columns that were not selected (sparse fieldsets on
     * `fields[webhook_deliveries]`).
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised delivery
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenAttributeSelected(
                'id',
                fn (): int => $this->resource->id,
            ),
            'uuid' => $this->whenAttributeSelected(
                'uuid',
                fn (): string => $this->resource->uuid,
            ),
            'event' => $this->whenAttributeSelected(
                'event',
                fn (): string => $this->resource->event,
            ),
            'status' => $this->whenAttributeSelected(
                'status',
                fn (): string => $this->resource->status->value,
            ),
            'attempts' => $this->whenAttributeSelected(
                'attempts',
                fn (): int => $this->resource->attempts,
            ),
            'last_status_code' => $this->whenAttributeSelected(
                'last_status_code',
                fn (): ?int => $this->resource->last_status_code,
            ),
            'last_error' => $this->whenAttributeSelected(
                'last_error',
                fn (): ?string => $this->resource->last_error,
            ),
            'delivered_at' => $this->whenAttributeSelected(
                'delivered_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->delivered_at),
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
        ];
    }
}
