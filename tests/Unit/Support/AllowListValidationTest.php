<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AllowListValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for allow-list validation message helpers.
 */
#[CoversClass(AllowListValidation::class)]
final class AllowListValidationTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Build a message that lists rejected and supported values.
     */
    #[Test]
    public function it_builds_a_message_that_lists_rejected_and_supported_values(): void
    {
        // Act

        $message = AllowListValidation::unsupportedMessage(
            'Unsupported Include',
            ['roles'],
            ['team', 'role'],
        );

        // Assert

        $this->assertSame(
            'Unsupported Include: roles (Supported: role, team)',
            $message,
        );
    }

    /**
     * Sort allow lists before serialising them.
     */
    #[Test]
    public function it_sorts_allow_lists_before_serialising_them(): void
    {
        // Act

        $sorted = AllowListValidation::sorted(['name', 'id', 'created_at']);

        // Assert

        $this->assertSame(['created_at', 'id', 'name'], $sorted);
    }
}
