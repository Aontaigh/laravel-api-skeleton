<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\User;
use App\Support\ApiDateTime;
use App\Support\CommaSeparatedList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a registered cookie-bound web session.
 *
 * @property-read \App\Models\WebSession $resource
 */
final class WebSessionResource extends JsonResource
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
     * Transform the Web Session into its API shape.
     *
     * Omits `user_id` unless the viewer holds `sessions.list-all`, and omits
     * `ip_address` / `user_agent` / location fields on another User's sessions
     * unless the viewer
     * holds `sessions.list-all` - a sparse-fieldset omission alone is not
     * enough, because a request that never constrains `fields[sessions]` runs
     * an unqualified `SELECT *` and would otherwise leak those columns.
     *
     * @param  Request              $request the inbound HTTP request
     * @return array<string, mixed> the serialised Web Session
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenAttributeSelected(
                'id',
                fn (): int => $this->resource->id,
            ),
            'user_id' => $this->when(
                array_key_exists('user_id', $this->resource->getAttributes())
                    && $request->user()?->can('sessions.list-all') === true,
                fn (): int => $this->resource->user_id,
            ),
            'device_name' => $this->whenAttributeSelected(
                'device_name',
                fn (): ?string => $this->resource->device_name,
            ),
            'ip_address' => $this->when(
                array_key_exists('ip_address', $this->resource->getAttributes())
                    && $this->viewerMaySeeSessionTelemetry($request),
                fn (): ?string => $this->resource->ip_address,
            ),
            'user_agent' => $this->when(
                array_key_exists('user_agent', $this->resource->getAttributes())
                    && $this->viewerMaySeeSessionTelemetry($request),
                fn (): ?string => $this->resource->user_agent,
            ),
            'location_city' => $this->when(
                array_key_exists('location_city', $this->resource->getAttributes())
                    && $this->viewerMaySeeSessionTelemetry($request),
                fn (): ?string => $this->resource->location_city,
            ),
            'location_country' => $this->when(
                array_key_exists('location_country', $this->resource->getAttributes())
                    && $this->viewerMaySeeSessionTelemetry($request),
                fn (): ?string => $this->resource->location_country,
            ),
            'remember_me' => $this->whenAttributeSelected(
                'remember_me',
                fn (): bool => $this->resource->remember_me,
            ),
            'last_activity_at' => $this->whenAttributeSelected(
                'last_activity_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->last_activity_at),
            ),
            'created_at' => $this->whenAttributeSelected(
                'created_at',
                fn (): ?string => ApiDateTime::serialize($this->resource->created_at),
            ),
            'is_current' => $this->when(
                $this->includesSessionField($request, 'is_current'),
                fn (): bool => $request->hasSession()
                    && $request->session()->getId() === $this->resource->session_id,
            ),
            'user' => $this->whenLoaded(
                'user',
                fn (User $user): UserResource => new UserResource($user),
            ),
        ];
    }

    /**
     * Whether the viewer may see IP, user-agent, and location telemetry for this session.
     *
     * Callers always see their own session metadata; cross-user telemetry
     * requires `sessions.list-all` (admin session management).
     *
     * @param  Request $request the inbound HTTP request
     * @return bool    true when telemetry may be shown to the viewer
     */
    private function viewerMaySeeSessionTelemetry(Request $request): bool
    {
        $viewer = $request->user();

        if ($viewer === null) {
            return false;
        }

        if ($viewer->can('sessions.list-all')) {
            return true;
        }

        return $this->resource->user_id === $viewer->id;
    }

    /**
     * Whether a computed Session field should appear for the active sparse fieldset.
     *
     * @param  Request $request the inbound HTTP request
     * @param  string  $field   the computed field name
     * @return bool    true when the computed field belongs in the response
     */
    private function includesSessionField(Request $request, string $field): bool
    {
        $fields = $request->query('fields');

        if (! is_array($fields) || ! array_key_exists('sessions', $fields)) {
            return true;
        }

        $raw = $fields['sessions'];

        if (! is_string($raw) || $raw === '') {
            return true;
        }

        return in_array($field, CommaSeparatedList::parse($raw), true);
    }
}
