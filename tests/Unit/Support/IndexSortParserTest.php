<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\DataTransferObjects\IndexSort;
use App\Support\IndexSortParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for `sort` query-param parsing.
 */
#[CoversClass(IndexSortParser::class)]
final class IndexSortParserTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('parseProvider')]
    public function it_parses_a_sort_value_into_column_and_direction(string $raw, IndexSort $expected): void
    {
        // Act

        $parsed = IndexSortParser::parse($raw);

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
     * @return array<string, array{0: string, 1: IndexSort}>
     */
    public static function parseProvider(): array
    {
        return [
            'ascending column' => ['name', new IndexSort(column: 'name', direction: 'asc')],
            'descending column' => ['-name', new IndexSort(column: 'name', direction: 'desc')],
            'double descending prefix' => ['--name', new IndexSort(column: '-name', direction: 'desc')],
            'leading whitespace trimmed' => [' -name ', new IndexSort(column: 'name', direction: 'desc')],
            'blank value' => ['', new IndexSort(column: '', direction: 'asc')],
            'whitespace only' => ['   ', new IndexSort(column: '', direction: 'asc')],
            'ascending with internal hyphen' => ['created_at', new IndexSort(column: 'created_at', direction: 'asc')],
        ];
    }
}
