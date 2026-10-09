<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TokenLifetime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for TokenLifetime.
 *
 * The cases that matter are the ones that would otherwise mint a token that
 * never expires or one that outlives the environment's ceiling: a configured
 * zero, a configured lifetime above the ceiling, and a ceiling that itself
 * resolves to zero.
 */
#[CoversClass(TokenLifetime::class)]
final class TokenLifetimeTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the configured ceiling.
     */
    #[Test]
    public function it_returns_the_configured_ceiling(): void
    {
        // Arrange

        config(['api.token_max_expiration_days' => 366]);

        // Act + Assert

        $this->assertSame(366, TokenLifetime::maximumDays());
    }

    /**
     * Floor the ceiling at one day so a configuration cannot disable expiry.
     */
    #[Test]
    public function it_floors_the_ceiling_at_one_day(): void
    {
        // Arrange

        config(['api.token_max_expiration_days' => 0]);

        // Act + Assert

        $this->assertSame(1, TokenLifetime::maximumDays());
    }

    /**
     * Pass a configured lifetime within the ceiling through unchanged.
     */
    #[Test]
    public function it_passes_a_lifetime_within_the_ceiling_through_unchanged(): void
    {
        // Arrange

        config(['api.token_max_expiration_days' => 366]);

        // Act + Assert

        $this->assertSame(90, TokenLifetime::boundedConfiguredDays(90));
    }

    /**
     * Cap a configured lifetime above the ceiling.
     */
    #[Test]
    public function it_caps_a_lifetime_above_the_ceiling(): void
    {
        // Arrange

        config(['api.token_max_expiration_days' => 30]);

        // Act + Assert

        $this->assertSame(30, TokenLifetime::boundedConfiguredDays(365));
    }

    /**
     * Fall back to the ceiling when a configured lifetime asks to never expire.
     */
    #[Test]
    public function it_falls_back_to_the_ceiling_when_the_lifetime_is_zero(): void
    {
        // Arrange

        config(['api.token_max_expiration_days' => 366]);

        // Act + Assert

        $this->assertSame(366, TokenLifetime::boundedConfiguredDays(0));
        $this->assertSame(366, TokenLifetime::boundedConfiguredDays(-1));
    }
}
