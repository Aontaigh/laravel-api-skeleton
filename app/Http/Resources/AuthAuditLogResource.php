<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\AuthAuditLog;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of an authentication audit log row.
 *
 * @property-read AuthAuditLog $resource
 */
final class AuthAuditLogResource extends JsonResource
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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenAttributeSelected(
                'id',
                fn (): int => $this->resource->id,
            ),
            'user_id' => $this->whenAttributeSelected(
                'user_id',
                fn (): ?int => $this->resource->user_id,
            ),
            'event' => $this->whenAttributeSelected(
                'event',
                fn (): string => $this->resource->event->value,
            ),
            'email' => $this->whenAttributeSelected(
                'email',
                fn (): ?string => $this->resource->email,
            ),
            'ip_address' => $this->whenAttributeSelected(
                'ip_address',
                fn (): ?string => $this->resource->ip_address,
            ),
            'user_agent' => $this->whenAttributeSelected(
                'user_agent',
                fn (): ?string => $this->resource->user_agent,
            ),
            'personal_access_token_id' => $this->whenAttributeSelected(
                'personal_access_token_id',
                fn (): ?int => $this->resource->personal_access_token_id,
            ),
            'api_client_id' => $this->whenAttributeSelected(
                'api_client_id',
                fn (): ?int => $this->resource->api_client_id,
            ),
            'remember_me' => $this->whenAttributeSelected(
                'remember_me',
                fn (): bool => $this->resource->remember_me,
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
            'user' => $this->whenLoaded(
                'user',
                fn (): UserResource => new UserResource($this->resource->user),
            ),
        ];
    }
}
