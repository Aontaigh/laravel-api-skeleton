<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\E164Phone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for canonical E.164 parsing and normalisation.
 */
#[CoversClass(E164Phone::class)]
final class E164PhoneTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Report whether the input is canonical E.164.
     *
     * @param string|null $input    the candidate phone value
     * @param bool        $expected whether the value should pass
     */
    #[Test]
    #[DataProvider('canonicalPhoneCases')]
    public function it_reports_whether_the_input_is_canonical_e164(?string $input, bool $expected): void
    {
        // Act + Assert

        self::assertSame($expected, E164Phone::isValid($input));
    }

    /**
     * Compact parseable display forms to canonical E.164.
     *
     * @param string|null $input    the candidate phone value
     * @param string|null $expected the canonical form, or null when not parseable
     */
    #[Test]
    #[DataProvider('normalizablePhoneCases')]
    public function it_normalizes_parseable_phone_numbers(?string $input, ?string $expected): void
    {
        // Act + Assert

        self::assertSame($expected, E164Phone::normalize($input));
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Parseable phone values mapped to their canonical E.164 form.
     *
     * @return array<string, array{0: ?string, 1: ?string}> the case name mapped to [input, expected]
     */
    public static function normalizablePhoneCases(): array
    {
        return [
            'irish mobile' => ['+353851046420', '+353851046420'],
            'spaced uk mobile' => ['+44 7700 900013', '+447700900013'],
            'trimmed canonical' => [' +353851046420 ', '+353851046420'],
            'local irish' => ['0851046420', null],
            'null' => [null, null],
        ];
    }

    /**
     * Candidate phone values mapped to whether they should pass.
     *
     * @return array<string, array{0: ?string, 1: bool}> the case name mapped to [input, expected]
     */
    public static function canonicalPhoneCases(): array
    {
        return [
            'irish mobile' => ['+353851046420', true],
            'uk mobile' => ['+447700900013', true],
            'us number' => ['+14155552671', true],
            'spaced display form' => ['+44 7700 900013', false],
            'punctuated landline' => ['+44 191 500 0012', false],
            'missing plus' => ['447700900013', false],
            'local irish' => ['0851046420', false],
            'null' => [null, false],
            'empty' => ['', false],
            'plus only' => ['+', false],
            'letters' => ['+44ABC900100', false],
        ];
    }
}
