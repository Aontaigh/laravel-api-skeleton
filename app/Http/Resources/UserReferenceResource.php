<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\User;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact identity an endpoint carries for an included User.
 *
 * A listing that names a person needs to name and link them, and nothing more.
 * `UserResource` is the profile payload: it emits roles, permissions, team, and
 * MFA state, which a page of rows must not repeat per party - a twenty-five row
 * page with `include=user` otherwise ships fifty permission arrays.
 *
 * Keys a `fields[users]` select left out are omitted rather than half-filled,
 * so a caller that asked for `id,name` receives exactly that. `email` and
 * `phone` additionally depend on the endpoint's nested allow-list, which never
 * carries them for a viewer without the matching permission.
 *
 * @property-read User $resource
 */
final class UserReferenceResource extends JsonResource
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
     * Transform the User reference into its API shape.
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised reference
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
            'email' => $this->whenAttributeSelected(
                'email',
                fn (): string => $this->resource->email,
            ),
            'phone' => $this->whenAttributeSelected(
                'phone',
                fn (): ?string => $this->resource->phone,
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
        ];
    }
}
