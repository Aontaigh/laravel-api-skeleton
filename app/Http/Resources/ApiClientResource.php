<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\ApiClient;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of an API client.
 *
 * @property-read ApiClient $resource
 */
final class ApiClientResource extends JsonResource
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
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
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
            'client_id' => $this->whenAttributeSelected(
                'client_id',
                fn (): string => $this->resource->client_id,
            ),
            'abilities' => $this->whenAttributeSelected(
                'abilities',
                fn (): array => $this->resource->abilities ?? [],
            ),
            'is_active' => $this->whenAttributeSelected(
                'is_active',
                fn (): bool => $this->resource->is_active,
            ),
            'last_used_at' => $this->whenAttributeSelected(
                'last_used_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->last_used_at),
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
        ];
    }
}
