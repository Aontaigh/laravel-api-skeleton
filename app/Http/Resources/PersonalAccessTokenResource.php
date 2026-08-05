<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API representation of a Sanctum Personal Access Token.
 *
 * The plaintext token value is never part of this shape — Sanctum only
 * returns it once, at creation time, and the controller merges it into the
 * response envelope alongside this Resource rather than through it.
 *
 * @property-read PersonalAccessToken $resource
 */
final class PersonalAccessTokenResource extends JsonResource
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
     * Transform the Token into its API shape.
     *
     * Omits keys for columns that were not selected (sparse fieldsets on `fields[tokens]`).
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised Token
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
            'abilities' => $this->whenAttributeSelected(
                'abilities',
                fn (): array => $this->resource->abilities ?? [],
            ),
            'last_used_at' => $this->whenAttributeSelected(
                'last_used_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->last_used_at),
            ),
            'expires_at' => $this->whenAttributeSelected(
                'expires_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->expires_at),
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
        ];
    }
}
