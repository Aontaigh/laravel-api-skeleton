<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Tokens;

use App\DataTransferObjects\Tokens\TokenFilters;
use App\Queries\Tokens\TokenFilterQuery;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for TokenFilterQuery filters.
 *
 * Constraints are asserted against the builder's own where list and bindings,
 * so these run with no database.
 */
#[CoversClass(TokenFilterQuery::class)]
final class TokenFilterQueryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_applies_no_filters_when_search_is_omitted(): void
    {
        // Arrange

        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query();

        /** @var TokenFilters $filters */
        $filters = new TokenFilters(search: null);

        // Act

        (new TokenFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame([], $query->getQuery()->wheres);
    }

    #[Test]
    #[DataProvider('searchTermProvider')]
    public function it_escapes_like_wildcards_in_the_search_term(string $term, string $expectedPattern): void
    {
        // Arrange

        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query();

        /** @var TokenFilters $filters */
        $filters = new TokenFilters(search: $term);

        // Act

        (new TokenFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame([$expectedPattern], $query->getBindings());
        $this->assertStringContainsString(
            'ESCAPE',
            (string) ($query->getQuery()->wheres[0]['sql'] ?? ''),
        );
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
    public static function searchTermProvider(): array
    {
        return [
            'plain term is wrapped only' => ['cli', '%cli%'],
            'match-all wildcard is escaped' => ['%', '%\%%'],
            'single-character wildcard is escaped' => ['_', '%\_%'],
            'escape character is doubled' => ['\\', '%\\\\%'],
            'mixed wildcards are escaped' => ['cli%_token', '%cli\%\_token%'],
        ];
    }
}
