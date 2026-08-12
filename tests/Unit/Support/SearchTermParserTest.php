<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SearchTermParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for `filter[search]` normalisation.
 */
#[CoversClass(SearchTermParser::class)]
final class SearchTermParserTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('normalizeProvider')]
    public function it_normalises_a_search_term(string $raw, ?string $expected): void
    {
        // Act

        $normalized = SearchTermParser::normalize($raw);

        // Assert

        $this->assertSame($expected, $normalized);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Raw search values mapped to the expected normalised term.
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'trimmed term' => ['  alice  ', 'alice'],
            'empty string' => ['', null],
            'blank after trim' => ['   ', null],
            'untrimmed term' => ['Acme', 'Acme'],
        ];
    }
}
