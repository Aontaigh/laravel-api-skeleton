<?php

declare(strict_types=1);

namespace Tests\Support\Http\Resources\Concerns;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal JsonResource exposing `whenAttributeSelected` for sparse column tests.
 *
 * @property-read User $resource
 */
final class SparseAttributesHarnessResource extends JsonResource
{
    use SerialisesSparseAttributes;

    /**
     * @return array<string, mixed> the serialised attributes
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->whenAttributeSelected('name', fn (): string => $this->resource->name),
        ];
    }
}
