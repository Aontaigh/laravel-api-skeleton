<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LikePattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for LikePattern.
 *
 * Pure string logic, so these run without a database.
 */
#[CoversClass(LikePattern::class)]
final class LikePatternTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Escape wildcards and wrap the term.
     */
    #[Test]
    /**
     * Escape wildcards and wrap the term.
     */
    #[DataProvider('containsProvider')]
    public function it_escapes_wildcards_and_wraps_the_term(string $term, string $expected): void
    {
        // Act

        $pattern = LikePattern::contains($term);

        // Assert

        $this->assertSame($expected, $pattern);
    }

    /**
     * Build a WHERE clause with an explicit escape character.
     */
    #[Test]
    public function it_builds_a_where_clause_with_an_explicit_escape_character(): void
    {
        // Act

        $clause = LikePattern::containsWhereClause('users.name');

        // Assert

        $this->assertSame("users.name LIKE ? ESCAPE '\\\\'", $clause);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Search terms mapped to the `LIKE` pattern they must produce.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [term, expectedPattern]
     */
    public static function containsProvider(): array
    {
        return [
            'plain term is wrapped only' => ['acme', '%acme%'],
            'empty term is wrapped only' => ['', '%%'],
            'match-all wildcard is escaped' => ['%', '%\%%'],
            'single-character wildcard is escaped' => ['_', '%\_%'],
            'escape character is doubled' => ['\\', '%\\\\%'],
            'mixed wildcards are escaped' => ['acme%_corp', '%acme\%\_corp%'],
            'sql-injection-shaped term is treated as a literal' => [
                "'; drop table users; --",
                "%'; drop table users; --%",
            ],
        ];
    }
}
