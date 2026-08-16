<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CommaSeparatedList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for comma-separated query-param parsing.
 */
#[CoversClass(CommaSeparatedList::class)]
final class CommaSeparatedListTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('parseProvider')]
    public function it_parses_trimmed_non_empty_segments(string $raw, array $expected): void
    {
        // Act

        $parsed = CommaSeparatedList::parse($raw);

        // Assert

        $this->assertSame($expected, $parsed);
    }

    /**
     * Return an empty list for a blank value.
     */
    #[Test]
    public function it_returns_an_empty_list_for_a_blank_value(): void
    {
        // Act

        $parsed = CommaSeparatedList::parse('   ,  , ');

        // Assert

        $this->assertSame([], $parsed);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Raw query values mapped to the expected trimmed list.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function parseProvider(): array
    {
        return [
            'spaces after commas' => ['id, name, email', ['id', 'name', 'email']],
            'empty segments skipped' => ['id,,name', ['id', 'name']],
            'single value' => ['team', ['team']],
            'injection-shaped segment without commas' => ["'; DROP TABLE users;--", ["'; DROP TABLE users;--"]],
            'unicode preserved' => ['id,naïve', ['id', 'naïve']],
        ];
    }
}
