<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AllowList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for allow-list comparison helpers.
 */
#[CoversClass(AllowList::class)]
final class AllowListTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return values that are not on the allow-list.
     */
    #[Test]
    public function it_returns_unsupported_values(): void
    {
        // Act

        $unsupported = AllowList::unsupported(['team', 'roles', 'team'], ['team', 'role']);

        // Assert

        $this->assertSame(['roles'], $unsupported);
    }

    /**
     * Return an empty list when every value is allowed.
     */
    #[Test]
    public function it_returns_an_empty_list_when_every_value_is_allowed(): void
    {
        // Act

        $unsupported = AllowList::unsupported(['id', 'name'], ['id', 'name', 'email']);

        // Assert

        $this->assertSame([], $unsupported);
    }

    /**
     * Return supported values in request order.
     */
    #[Test]
    public function it_returns_supported_values_in_request_order(): void
    {
        // Act

        $supported = AllowList::supported(['name', 'id', 'email', 'name'], ['id', 'name', 'email']);

        // Assert

        $this->assertSame(['name', 'id', 'email', 'name'], $supported);
    }

    /**
     * Return an empty list when nothing requested is allowed.
     */
    #[Test]
    public function it_returns_an_empty_list_when_nothing_requested_is_allowed(): void
    {
        // Act

        $supported = AllowList::supported(['roles', 'permissions'], ['team', 'role']);

        // Assert

        $this->assertSame([], $supported);
    }
}
