<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CommaListParser;
use App\Support\CommaSeparatedList;
use App\Support\ListTooLongException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for CommaListParser.
 *
 * The cases that matter are the ones a caller can actually produce by accident: padding, repeated
 * values, an empty segment, a blank parameter, and a list past the cap.
 */
#[CoversClass(CommaListParser::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(ListTooLongException::class)]
final class CommaListParserTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Split a single value into a list of one.
     */
    #[Test]
    public function it_returns_a_single_value_as_a_list_of_one(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split('3');

        // Assert

        $this->assertSame(['3'], $parts);
    }

    /**
     * Split a comma-separated list into its parts.
     */
    #[Test]
    public function it_splits_a_comma_separated_list(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split('1,2,3');

        // Assert

        $this->assertSame(['1', '2', '3'], $parts);
    }

    /**
     * Trim padding around each value so `1, 2` and `1,2` agree.
     */
    #[Test]
    public function it_trims_whitespace_around_each_value(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split(' 1 , 2 ,3 ');

        // Assert

        $this->assertSame(['1', '2', '3'], $parts);
    }

    /**
     * Drop a repeated value so `1,1,2` cannot widen the SQL.
     */
    #[Test]
    public function it_removes_duplicate_values_keeping_first_seen_order(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split('3,1,3,2,1');

        // Assert

        $this->assertSame(['3', '1', '2'], $parts);
    }

    /**
     * Drop empty segments so a trailing comma is not an empty value.
     */
    #[Test]
    public function it_drops_empty_segments(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split('1,,2,');

        // Assert

        $this->assertSame(['1', '2'], $parts);
    }

    /**
     * Return an empty list for an absent, empty, or whitespace-only parameter.
     */
    #[Test]
    #[DataProvider('blankValues')]
    public function it_returns_an_empty_list_for_a_blank_value(?string $raw): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split($raw);

        // Assert

        $this->assertSame([], $parts);
    }

    /**
     * Cast each part to an integer for ID filters.
     */
    #[Test]
    public function it_casts_parts_to_integers(): void
    {
        // Arrange

        // Act

        $ids = CommaListParser::integers(' 10, 20 ');

        // Assert

        $this->assertSame([10, 20], $ids);
    }

    /**
     * Cast a non-numeric part to zero rather than dropping it, so validation still sees it.
     *
     * Silently dropping an unparseable value would widen the result set without telling the
     * caller, which is the failure mode this parser exists to avoid.
     */
    #[Test]
    public function it_casts_a_non_numeric_part_rather_than_dropping_it(): void
    {
        // Arrange

        // Act

        $ids = CommaListParser::integers('1,abc');

        // Assert

        $this->assertSame([1, 0], $ids);
    }

    /**
     * Raise rather than truncate a list past the cap.
     *
     * Truncating would return a partial answer that looks complete.
     */
    #[Test]
    public function it_raises_when_the_list_exceeds_its_cap(): void
    {
        // Arrange

        // Act

        try {
            CommaListParser::split('1,2,3', 2);
            $this->fail('Expected a ListTooLongException.');
        } catch (ListTooLongException $exception) {
            // Assert

            $this->assertSame(3, $exception->given);
            $this->assertSame(2, $exception->max);
        }
    }

    /**
     * Count the non-empty parts before de-duplication, so `1,1,1` cannot smuggle past a cap of two.
     */
    #[Test]
    public function it_counts_parts_before_deduplication(): void
    {
        // Arrange

        // Act

        try {
            CommaListParser::split('1,1,1', 2);
            $this->fail('Expected a ListTooLongException.');
        } catch (ListTooLongException $exception) {
            // Assert

            $this->assertSame(3, $exception->given);
        }
    }

    /**
     * Blanks are not values, so a list of nothing but commas never trips a cap.
     *
     * Counting raw segments made this depend on which filter carried the value: `,,,` was a 422
     * against a cap of three and a 200 against a cap of fifty, for the same semantically empty
     * input. The same input must answer the same way regardless of the filter, so the cap counts
     * only the parts that survive trimming.
     */
    #[Test]
    public function it_does_not_count_blank_segments_towards_the_cap(): void
    {
        // Arrange

        $segments = ['1', '2', '3', '4', '5'];

        // Act

        $blankPadded = CommaListParser::split(',,'.implode(',', $segments).',,,', 5);

        // Assert

        $this->assertSame($segments, $blankPadded, 'Padding with commas must not count towards the cap.');
    }

    /**
     * A value of nothing but commas is an absent filter, not an oversized one.
     *
     * Pinned against the smallest cap in the schema so the case cannot regress by being argued
     * away as an artefact of a generous cap.
     */
    #[Test]
    public function it_treats_a_comma_only_value_as_absent_at_any_cap(): void
    {
        // Arrange

        // Act

        // Assert

        $this->assertSame([], CommaListParser::split(',,,', 1));
        $this->assertSame([], CommaListParser::split(',', 1));
    }

    /**
     * Accept a list exactly at the cap.
     */
    #[Test]
    public function it_accepts_a_list_exactly_at_the_cap(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split('1,2,3', 3);

        // Assert

        $this->assertSame(['1', '2', '3'], $parts);
    }

    /**
     * Keep the token `"0"` through parsing, de-duplication, and the integer cast.
     *
     * A bare `array_filter()` drops `"0"` as falsy, which would turn an explicit
     * ID of 0 into an omitted filter - the pin for the explicit `!== ''` guard
     * in `CommaSeparatedList::parse()`.
     */
    #[Test]
    public function it_keeps_the_zero_token(): void
    {
        // Arrange

        // Act

        $parts = CommaListParser::split('0,1,0');

        // Assert

        $this->assertSame(['0', '1'], $parts);
        $this->assertSame([0, 1], CommaListParser::integers('0,1,0'));
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Values that mean "no filter supplied".
     *
     * @return array<string, array{0: string|null}>
     */
    public static function blankValues(): array
    {
        return [
            'absent' => [null],
            'empty string' => [''],
            'single space' => [' '],
            'commas only' => [',,,'],
            'commas and spaces' => [' , , '],
        ];
    }
}
