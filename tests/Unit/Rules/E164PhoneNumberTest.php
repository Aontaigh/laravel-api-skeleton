<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\E164PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the canonical E.164 validation rule.
 */
#[CoversClass(E164PhoneNumber::class)]
final class E164PhoneNumberTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Accept canonical E.164 and reject spaced or local forms.
     *
     * @param string $input    the candidate phone value
     * @param bool   $expected whether the value should pass
     */
    #[Test]
    #[DataProvider('phoneValidationCases')]
    public function it_validates_canonical_e164_phone_inputs(string $input, bool $expected): void
    {
        // Arrange

        /** @var list<string> $failures */
        $failures = [];

        // Act

        (new E164PhoneNumber)->validate('phone', $input, static function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        // Assert

        if ($expected) {
            self::assertSame([], $failures);

            return;
        }

        self::assertSame(['Must Be in E.164 Format (e.g. +353851046420)'], $failures);
    }

    /**
     * Fail a non-string value without reaching libphonenumber.
     */
    #[Test]
    public function it_fails_a_non_string_value(): void
    {
        // Arrange

        /** @var list<string> $failures */
        $failures = [];

        // Act

        (new E164PhoneNumber)->validate('phone', 353851046420, static function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        // Assert

        self::assertCount(1, $failures);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Candidate phone values mapped to whether they should pass.
     *
     * @return array<string, array{0: string, 1: bool}> the case name mapped to [input, expected]
     */
    public static function phoneValidationCases(): array
    {
        return [
            'irish mobile' => ['+353851046420', true],
            'uk mobile' => ['+447700900013', true],
            'us number' => ['+14155552671', true],
            'spaced display form' => ['+44 7700 900013', false],
            'missing plus' => ['447700900013', false],
            'local irish' => ['0851046420', false],
            'letters' => ['+44ABC900100', false],
            'plus only' => ['+', false],
        ];
    }
}
