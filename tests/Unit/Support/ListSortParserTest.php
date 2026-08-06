<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\DataTransferObjects\ListSort;
use App\Support\ListSortParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for `sort` query-param parsing.
 */
#[CoversClass(ListSortParser::class)]
final class ListSortParserTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('parseProvider')]
    public function it_parses_a_sort_value_into_column_and_direction(string $raw, ListSort $expected): void
    {
        // Act

        $parsed = ListSortParser::parse($raw);

        // Assert

        $this->assertSame($expected->column, $parsed->column);
        $this->assertSame($expected->direction, $parsed->direction);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Raw sort values mapped to the expected column and direction.
     *
     * @return array<string, array{0: string, 1: ListSort}>
     */
    public static function parseProvider(): array
    {
        return [
            'ascending column' => ['name', new ListSort(column: 'name', direction: 'asc')],
            'descending column' => ['-name', new ListSort(column: 'name', direction: 'desc')],
            'double descending prefix' => ['--name', new ListSort(column: '-name', direction: 'desc')],
            'leading whitespace trimmed' => [' -name ', new ListSort(column: 'name', direction: 'desc')],
            'blank value' => ['', new ListSort(column: '', direction: 'asc')],
            'whitespace only' => ['   ', new ListSort(column: '', direction: 'asc')],
            'ascending with internal hyphen' => ['created_at', new ListSort(column: 'created_at', direction: 'asc')],
        ];
    }
}
