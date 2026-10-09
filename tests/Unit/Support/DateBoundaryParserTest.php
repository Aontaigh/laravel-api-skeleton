<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DateBoundaryParser;
use Carbon\Exceptions\InvalidFormatException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for DateBoundaryParser.
 *
 * The cases that matter are the ones that silently bind the wrong boundary: a bare date that must
 * expand to the whole day, a colon-free date-time that must not be mistaken for a bare date, and
 * an offset-bearing instant that must normalise to UTC before it meets a UTC column.
 */
#[CoversClass(DateBoundaryParser::class)]
final class DateBoundaryParserTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a bare date lower bound to the start of that UTC day.
     */
    #[Test]
    public function it_resolves_a_bare_from_date_to_the_start_of_the_day(): void
    {
        // Arrange

        // Act

        $boundary = DateBoundaryParser::resolve('2026-10-05', endOfDay: false);

        // Assert

        $this->assertSame('2026-10-05 00:00:00', $boundary->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $boundary->tzName);
    }

    /**
     * Resolve a bare date upper bound to the end of that UTC day.
     */
    #[Test]
    public function it_resolves_a_bare_to_date_to_the_end_of_the_day(): void
    {
        // Arrange

        // Act

        $boundary = DateBoundaryParser::resolve('2026-10-05', endOfDay: true);

        // Assert

        $this->assertSame('2026-10-05 23:59:59', $boundary->format('Y-m-d H:i:s'));
    }

    /**
     * Resolve a non-padded bare date exactly like its padded form.
     */
    #[Test]
    public function it_resolves_a_non_padded_bare_date_to_the_whole_day(): void
    {
        // Arrange

        // Act

        $from = DateBoundaryParser::resolve('2026-10-5', endOfDay: false);
        $to = DateBoundaryParser::resolve('2026-10-5', endOfDay: true);

        // Assert

        $this->assertSame('2026-10-05 00:00:00', $from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 23:59:59', $to->format('Y-m-d H:i:s'));
    }

    /**
     * Pass a full date-time through untouched for either bound.
     */
    #[Test]
    public function it_passes_a_full_datetime_through_as_the_named_instant(): void
    {
        // Arrange

        // Act

        $from = DateBoundaryParser::resolve('2026-10-05 15:00:00', endOfDay: false);
        $to = DateBoundaryParser::resolve('2026-10-05 15:00:00', endOfDay: true);

        // Assert

        $this->assertSame('2026-10-05 15:00:00', $from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 15:00:00', $to->format('Y-m-d H:i:s'));
    }

    /**
     * Normalise an offset-bearing instant to UTC before it meets a UTC column.
     */
    #[Test]
    public function it_normalises_an_offset_datetime_to_utc(): void
    {
        // Arrange

        // Act

        $boundary = DateBoundaryParser::resolve('2026-10-05T15:00:00+02:00', endOfDay: false);

        // Assert

        $this->assertSame('2026-10-05 13:00:00', $boundary->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $boundary->tzName);
    }

    /**
     * Never mistake a colon-free date-time for a bare calendar date.
     */
    #[Test]
    public function it_does_not_expand_a_colon_free_datetime_to_the_whole_day(): void
    {
        // Arrange

        // Act

        $boundary = DateBoundaryParser::resolve('2026-10-05T15Z', endOfDay: true);

        // Assert

        $this->assertSame('2026-10-05 15:00:00', $boundary->format('Y-m-d H:i:s'));
    }

    /**
     * Reject a value Carbon cannot parse, so callers fall through to the `date` rule.
     */
    #[Test]
    public function it_rejects_an_unparseable_value(): void
    {
        // Arrange

        // Act

        try {
            DateBoundaryParser::resolve('not-a-date', endOfDay: false);

            $this->fail('Resolving an unparseable value should throw.');
        } catch (InvalidFormatException $exception) {
            // Assert

            $this->assertNotSame('', $exception->getMessage());
        }
    }

    /**
     * Detect bare calendar dates by shape.
     *
     * @param string $value the input value
     * @param bool   $bare  whether the value counts as a bare calendar date
     */
    #[Test]
    #[DataProvider('bareDateShapes')]
    public function it_detects_bare_calendar_dates_by_shape(string $value, bool $bare): void
    {
        // Arrange

        // Act

        $actual = DateBoundaryParser::isBareCalendarDate($value);

        // Assert

        $this->assertSame($bare, $actual);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Bare-date shapes with their expected verdicts.
     *
     * @return array<string, array{string, bool}>
     */
    public static function bareDateShapes(): array
    {
        return [
            'padded date' => ['2026-10-05', true],
            'non-padded date' => ['2026-10-5', true],
            'padded date with whitespace' => ['  2026-10-05  ', true],
            'datetime with space separator' => ['2026-10-05 15:00:00', false],
            'datetime with T separator' => ['2026-10-05T15:00:00', false],
            'colon-free datetime' => ['2026-10-05T15Z', false],
            'colon-free datetime with offset' => ['2026-10-05 +0200', false],
            'datetime with offset' => ['2026-10-05T15:00:00+02:00', false],
            'embedded newline is not a bare date' => ["2026-10-05\n15:00", false],
        ];
    }
}
