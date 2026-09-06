<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Teams;

use App\Models\Team;
use App\Queries\Teams\TeamFilterQuery;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the Team filter query: LIKE-escape and search-term handling.
 *
 * Mirrors [UserFilterQueryTest](../Users/UserFilterQueryTest.php) - the LIKE
 * grammar is shared, but the escape behaviour is pinned per query class so a
 * regression in one resource cannot hide behind another's green suite.
 */
#[CoversClass(TeamFilterQuery::class)]
final class TeamFilterQueryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the Team filter query for the given search term.
     *
     * @param  string        $term the raw search term
     * @return Builder<Team> the filtered query builder
     */
    private function filteredQuery(string $term): Builder
    {
        $query = Team::query();

        (new TeamFilterQuery)->apply($query, new \App\DataTransferObjects\Teams\TeamFilters(search: $term));

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Wrap the search term in LIKE wildcards.
     */
    #[Test]
    public function it_wraps_the_search_term_in_wildcards(): void
    {
        // Act

        $query = $this->filteredQuery('engineering');

        // Assert

        $this->assertSame(['%engineering%'], $query->getQuery()->getRawBindings()['where']);
    }

    /**
     * Escape user-supplied LIKE wildcards so `%` and `_` stay literal.
     *
     * @param string $term   the raw search term
     * @param string $stored the escaped pattern expected in the bindings
     */
    #[DataProvider('wildcardProvider')]
    #[Test]
    public function it_escapes_like_wildcards_in_the_search_term(string $term, string $stored): void
    {
        // Act

        $query = $this->filteredQuery($term);

        // Assert

        $this->assertSame([$stored], $query->getQuery()->getRawBindings()['where']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Return adversarial search terms and their escaped patterns.
     *
     * @return array<string, array<int, string>> case name mapped to [term, escaped]
     */
    public static function wildcardProvider(): array
    {
        return [
            'match-all wildcard is escaped' => ['%', '%\\%%'],
            'single-character wildcard is escaped' => ['_', '%\\_%'],
            'escape character is doubled' => ['\\', '%\\\\%'],
            'mixed wildcards are escaped' => ['a%_b', '%a\\%\\_b%'],
        ];
    }
}
