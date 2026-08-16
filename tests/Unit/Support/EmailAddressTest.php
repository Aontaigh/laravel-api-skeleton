<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\EmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for email normalisation.
 */
#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Lowercase email addresses before persistence.
     */
    #[Test]
    #[DataProvider('emailProvider')]
    public function it_lowercases_email_addresses(string $input, string $expected): void
    {
        // Act

        $normalised = EmailAddress::normalise($input);

        // Assert

        $this->assertSame($expected, $normalised);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Raw email values mapped to the expected normalised form.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [input, expected]
     */
    public static function emailProvider(): array
    {
        return [
            'already lowercase' => ['alice@example.com', 'alice@example.com'],
            'uppercase local part' => ['ALICE@example.com', 'alice@example.com'],
            'uppercase domain' => ['alice@EXAMPLE.COM', 'alice@example.com'],
            'mixed case' => ['Alice@Example.COM', 'alice@example.com'],
        ];
    }
}
