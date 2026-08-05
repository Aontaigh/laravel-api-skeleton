<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the ResolvesAuthenticatedViewer trait.
 *
 * Exercised against a minimal host implementing only the abstract `user()`
 * contract, so these run with no database and no real FormRequest.
 */
#[CoversTrait(ResolvesAuthenticatedViewer::class)]
final class ResolvesAuthenticatedViewerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_the_authenticated_user(): void
    {
        // Arrange

        /** @var User $expected */
        $expected = new User(['name' => 'Authenticated Viewer']);

        $host = new FakeViewerHost($expected);

        // Act

        $viewer = $host->viewer();

        // Assert

        $this->assertSame($expected, $viewer);
    }

    #[Test]
    public function it_throws_when_no_authenticated_user_is_present(): void
    {
        /*
         * `viewer()` is only ever called after `authorize()` has already
         * confirmed a User is present, so this is a wiring-fault guard
         * rather than a client-facing error path — it should never fire in
         * production, but the throw itself still needs proving.
         */

        // Arrange

        $host = new FakeViewerHost(null);

        // Act + Assert

        $this->expectException(AuthenticationException::class);

        $host->viewer();
    }
}

/**
 * Minimal `ResolvesAuthenticatedViewer` host for the tests above.
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
