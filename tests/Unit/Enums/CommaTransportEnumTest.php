<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AuthAuditEvent;
use App\Enums\RoleName;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use BackedEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Pin the invariant that makes the comma-separated list filter transport safe.
 */
#[CoversClass(AuthAuditEvent::class)]
#[CoversClass(RoleName::class)]
#[CoversClass(WebhookDeliveryStatus::class)]
#[CoversClass(WebhookEvent::class)]
final class CommaTransportEnumTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * No value of an enum served by a comma-separated filter contains a comma.
     *
     * The transport is only safe for a closed vocabulary because values cannot
     * themselves carry the separator: a case that gained a comma would split
     * into two phantom filter values at the client and 422 for reasons nobody
     * could see in the payload they sent.
     *
     * @param  class-string<BackedEnum> $enumClass the enum under test
     * @return void
     */
    #[Test]
    #[DataProvider('commaTransportEnumProvider')]
    public function it_never_contains_a_comma_in_a_value(string $enumClass): void
    {
        // Arrange

        // Act

        $values = array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $enumClass::cases(),
        );

        // Assert

        foreach ($values as $value) {
            $this->assertStringNotContainsString(',', $value);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Enums whose values travel in comma-separated list filters.
     *
     * @return array<string, array{0: class-string<BackedEnum>}>
     */
    public static function commaTransportEnumProvider(): array
    {
        return [
            'AuthAuditEvent' => [AuthAuditEvent::class],
            'RoleName' => [RoleName::class],
            'WebhookDeliveryStatus' => [WebhookDeliveryStatus::class],
            'WebhookEvent' => [WebhookEvent::class],
        ];
    }
}
