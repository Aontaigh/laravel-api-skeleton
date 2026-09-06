<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SystemHealth;

use App\Contracts\SystemHealth\SystemHealthCheck;
use App\Providers\SystemHealthServiceProvider;
use App\Services\SystemHealth\SystemHealthCheckRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;
use UnexpectedValueException;

/**
 * Unit tests for the System Health Check Registry.
 *
 * The provider is registered in `bootstrap/providers.php`, so the three
 * production checks are already tagged against the booted container.
 */
#[CoversClass(SystemHealthCheckRegistry::class)]
#[CoversClass(SystemHealthServiceProvider::class)]
final class SystemHealthCheckRegistryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve every container-tagged check implementation.
     */
    #[Test]
    public function it_resolves_every_tagged_check(): void
    {
        // Arrange

        $registry = app(SystemHealthCheckRegistry::class);

        // Act

        $checks = $registry->all();

        // Assert

        $this->assertCount(3, $checks);
        $this->assertContainsOnlyInstancesOf(SystemHealthCheck::class, $checks);
    }

    /**
     * List every monitored component slug without running any probe.
     */
    #[Test]
    public function it_lists_every_monitored_component_slug(): void
    {
        // Arrange

        $registry = app(SystemHealthCheckRegistry::class);

        // Act

        $components = $registry->components();

        // Assert

        $this->assertSame(['database', 'cache', 'queue'], $components);
    }

    /**
     * Reject a class mistakenly tagged under the System Health Checks tag.
     */
    #[Test]
    public function it_rejects_a_tagged_class_that_is_not_a_system_health_check(): void
    {
        // Arrange

        $this->app->tag([stdClass::class], SystemHealthServiceProvider::CHECKS_TAG);

        $registry = app(SystemHealthCheckRegistry::class);

        // Expect

        $this->expectException(UnexpectedValueException::class);

        // Act

        $registry->all();
    }
}
