<?php

declare(strict_types=1);

namespace Tests\Support\Http\Resources\Concerns;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

/**
 * Harness that throws when the email Closure runs — proves lazy evaluation.
 *
 * @property-read User $resource
 */
final class SparseAttributesClosureHarnessResource extends JsonResource
{
    use SerialisesSparseAttributes;

    /**
     * @return array<string, mixed> the serialised attributes
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->whenAttributeSelected('email', function (): string {
                throw new RuntimeException('Email Closure Should Not Run When Email Was Not Selected');
            }),
        ];
    }
}
