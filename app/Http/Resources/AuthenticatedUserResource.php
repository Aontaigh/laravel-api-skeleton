<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of the authenticated User on login and registration.
 *
 * Always includes `email` for the session owner — unlike {@see UserResource},
 * which gates email behind `users.view-email`.
 *
 * @property-read User $resource
 */
final class AuthenticatedUserResource extends JsonResource
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Transform the authenticated User into its API shape.
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised User
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'created_at' => ApiDateTime::serialize($this->resource->created_at),
        ];
    }
}
