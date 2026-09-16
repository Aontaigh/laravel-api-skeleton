<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AuthTimingHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit tests for the lazy, per-process timing-normalisation hash.
 */
#[CoversClass(AuthTimingHash::class)]
final class AuthTimingHashTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Reset the process memoisation and any test config override between
     * tests, so each case starts from a clean resolution path.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $property = new ReflectionClass(AuthTimingHash::class)->getProperty('hash');
        $property->setValue(null, null);

        Config::set('api.auth_timing_normalisation_hash', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return a valid Argon2id hash of the configured driver.
     */
    #[Test]
    public function it_generates_a_placeholder_hash_when_unconfigured(): void
    {
        // Act

        $hash = AuthTimingHash::value();

        // Assert

        self::assertNotSame('', $hash);
        self::assertTrue(password_verify('auth-timing-normalisation', $hash));
    }

    /**
     * Return the same hash on every read within one process.
     */
    #[Test]
    public function it_memoises_the_hash_per_process(): void
    {
        // Act

        $first = AuthTimingHash::value();
        $second = AuthTimingHash::value();

        // Assert

        self::assertSame($first, $second);
    }

    /**
     * Honour a configured value over the generated placeholder.
     */
    #[Test]
    public function it_prefers_a_configured_value(): void
    {
        // Arrange

        Config::set('api.auth_timing_normalisation_hash', '$argon2id$pin-me');

        // Act

        $hash = AuthTimingHash::value();

        // Assert

        self::assertSame('$argon2id$pin-me', $hash);
    }

    /**
     * Treat an empty configured value as absent so the placeholder is used.
     */
    #[Test]
    public function it_treats_an_empty_configured_value_as_absent(): void
    {
        // Arrange

        Config::set('api.auth_timing_normalisation_hash', '');

        // Act

        $hash = AuthTimingHash::value();

        // Assert

        self::assertTrue(password_verify('auth-timing-normalisation', $hash));
    }
}
