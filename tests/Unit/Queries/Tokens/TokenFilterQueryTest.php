<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Tokens;

use App\DataTransferObjects\Tokens\TokenFilters;
use App\Models\User;
use App\Queries\Tokens\TokenFilterQuery;
use App\Queries\Tokens\TokenQueryConstraints;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for TokenFilterQuery row scoping and filters.
 *
 * Constraints are asserted against the builder's own where list and bindings,
 * so these run with no database.
 */
#[CoversClass(TokenFilterQuery::class)]
final class TokenFilterQueryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------​
    | Constants
    |--------------------------------------------------------------------------​
    */

    /** The authenticated viewer's ID. */
    private const VIEWER_ID = 42;

    /*
    |--------------------------------------------------------------------------​
    | Setup
    |--------------------------------------------------------------------------​
    */

    /**
     * Build an unsaved viewer for row-scoping assertions.
     *
     * @return User a viewer whose tokens the query must scope to
     */
    private function viewer(): User
    {
        $viewer = new User;
        $viewer->id = self::VIEWER_ID;

        return $viewer;
    }

    /*
    |--------------------------------------------------------------------------​
    | Tests
    |--------------------------------------------------------------------------​
    */

    /**
     * Scope Tokens to the viewer.
     */
    #[Test]
    public function it_scopes_tokens_to_the_viewer(): void
    {
        // Arrange

        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query();

        /** @var TokenFilters $filters */
        $filters = new TokenFilters(viewer: $this->viewer());

        // Act

        (new TokenFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame(
            TokenQueryConstraints::TABLE.'.tokenable_id',
            $query->getQuery()->wheres[0]['column'],
        );
        $this->assertSame([self::VIEWER_ID], $query->getBindings());
    }

    /**
     * Apply only row scoping when search is omitted.
     */
    #[Test]
    public function it_applies_only_row_scoping_when_search_is_omitted(): void
    {
        // Arrange

        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query();

        /** @var TokenFilters $filters */
        $filters = new TokenFilters(viewer: $this->viewer());

        // Act

        (new TokenFilterQuery)->apply($query, $filters);

        // Assert

        /*
         * Exactly one where clause (the row scope), no search where.
         */

        $this->assertCount(1, $query->getQuery()->wheres);
    }

    /**
     * Escape LIKE wildcards in the search term.
     */
    #[Test]
    /**
     * Escape LIKE wildcards in the search term.
     */
    #[DataProvider('searchTermProvider')]
    public function it_escapes_like_wildcards_in_the_search_term(string $term, string $expectedPattern): void
    {
        /*
         * An unescaped `%` would turn any search into a match-all, and a term
         * dense in `_` turns a scan into a CPU burn. Both wildcards must reach
         * the database as literal characters.
         */

        // Arrange

        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query();

        /** @var TokenFilters $filters */
        $filters = new TokenFilters(
            viewer: $this->viewer(),
            search: $term,
        );

        // Act

        (new TokenFilterQuery)->apply($query, $filters);

        // Assert

        /*
         * The row-scope binding comes first, the search pattern second.
         */

        $this->assertSame([self::VIEWER_ID, $expectedPattern], $query->getBindings());

        $this->assertStringContainsString(
            'ESCAPE',
            (string) ($query->getQuery()->wheres[1]['sql'] ?? ''),
        );
    }

    /*
    |--------------------------------------------------------------------------​
    | Data Providers
    |--------------------------------------------------------------------------​
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
            'match-all wildcard is escaped' => ['%', '%\\%%'],
            'single-character wildcard is escaped' => ['_', '%\\_%'],
            'escape character is doubled' => ['\\\\', '%\\\\\\\\%'],
            'mixed wildcards are escaped' => ['cli%_token', '%cli\%\_token%'],
        ];
    }
}
