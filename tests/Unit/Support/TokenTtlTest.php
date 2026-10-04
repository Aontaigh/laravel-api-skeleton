<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TokenTtl;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TokenTtl::class)]
final class TokenTtlTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Derive the response TTL from the token's own expiry, not a configured
     * window: if any time elapses between minting and rendering, the
     * configured window would overstate the remaining life.
     */
    #[Test]
    public function it_derives_the_remaining_seconds_from_the_token_expiry(): void
    {
        // Arrange

        $now = Carbon::parse('2026-01-01 12:00:00');
        $expiresAt = Carbon::parse('2026-01-11 12:00:00');

        // Act

        $seconds = TokenTtl::secondsFor($expiresAt, $now);

        // Assert

        $this->assertSame(864000, $seconds);
    }

    /**
     * Report no TTL for a token that never expires.
     */
    #[Test]
    public function it_reports_no_ttl_for_a_never_expiring_token(): void
    {
        // Act + Assert

        $this->assertNull(TokenTtl::secondsFor(null, Carbon::now()));
    }

    /**
     * Clamp the TTL at zero for an expiry already in the past, so a slow
     * response can never advertise negative seconds to the caller.
     */
    #[Test]
    public function it_clamps_an_already_elapsed_expiry_to_zero(): void
    {
        // Arrange

        $now = Carbon::parse('2026-01-01 12:00:00');
        $expiresAt = Carbon::parse('2025-12-31 12:00:00');

        // Act + Assert

        $this->assertSame(0, TokenTtl::secondsFor($expiresAt, $now));
    }
}
