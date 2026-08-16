<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\Team;
use App\Models\User;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a User.
 *
 * @property-read User $resource
 */
final class UserResource extends JsonResource
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
     * Transform the User into its API shape.
     *
     * Omits keys for columns that were not selected (sparse fieldsets), and
     * omits `email` unless the requesting User holds `users.view-email` or is
     * viewing their own record — a sparse-fieldset omission alone is not enough,
     * because a request that never constrains `fields[users]` runs an unqualified
     * `SELECT *` and would otherwise leak the column regardless of permission.
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised User
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
            'email' => $this->when(
                array_key_exists('email', $this->resource->getAttributes())
                    && (
                        $request->user()?->is($this->resource) === true
                        || $request->user()?->can('users.view-email') === true
                    ),
                fn (): string => $this->resource->email,
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),

            /*
             * Relations
             *
             * Only present when the matching 'include' was eager-loaded.
             */
            'team' => $this->whenLoaded(
                'team',
                fn (Team $team): TeamResource => new TeamResource($team),
            ),
            'role' => $this->when(
                $this->resource->relationLoaded('roles') && $this->resource->roles->isNotEmpty(),
                fn (): RoleResource => new RoleResource($this->resource->roles->first()),
            ),
        ];
    }
}
