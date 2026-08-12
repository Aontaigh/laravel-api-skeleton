<?php

declare(strict_types=1);

namespace Tests\Support\Http\Requests\Concerns;

use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Models\User;

/**
 * Minimal `ResolvesAuthenticatedViewer` host for unit tests.
 *
 * A named class, rather than an anonymous class per scenario, keeps
 * `user()`'s declared `?User` return type honest for both the
 * authenticated and guest cases: static analysis of the trait's
 * `instanceof` guard sees the same genuinely-nullable signature either
 * way, instead of a per-literal type narrowed to whichever single
 * scenario that literal happened to construct.
 */
final class FakeViewerHost
{
    use ResolvesAuthenticatedViewer;

    public function __construct(private readonly ?User $authenticated) {}

    public function user($guard = null): ?User
    {
        return $this->authenticated;
    }
}
