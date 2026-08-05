<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a Team (minimal shape for nested includes).
 *
 * @property-read Team $resource
 */
final class TeamResource extends JsonResource
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
     * Transform the Team into its API shape.
     *
     * Omits keys for columns that were not selected (sparse fieldsets on `fields[teams]`).
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised Team
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
        ];
    }
}
