<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * API representation of a Role.
 *
 * @property-read Role $resource
 */
final class RoleResource extends JsonResource
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
     * Transform the Role into its API shape.
     *
     * Omits keys for columns that were not selected (sparse fieldsets on `fields[roles]`).
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised Role
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenAttributeSelected(
                'id',
                fn () => $this->resource->id,
            ),
            'name' => $this->whenAttributeSelected(
                'name',
                fn (): string => $this->resource->name,
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),

            /*
             * Relations
             *
             * Only present when 'include=permissions' was eager-loaded.
             */
            'permissions' => $this->whenLoaded(
                'permissions',
                fn (): AnonymousResourceCollection => PermissionResource::collection(
                    $this->resource->permissions,
                ),
            ),
        ];
    }
}
