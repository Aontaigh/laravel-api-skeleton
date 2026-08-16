<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Users;

use App\DataTransferObjects\Users\UserFilters;
use App\Models\User;
use App\Queries\Users\UserFilterQuery;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for UserFilterQuery row scoping and filters.
 *
 * Constraints are asserted against the builder's own where list and bindings,
 * so these run with no database.
 */
#[CoversClass(UserFilterQuery::class)]
final class UserFilterQueryTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Team the non-admin viewer belongs to. */
    private const VIEWER_TEAM_ID = 7;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build an unsaved viewer scoped to a single Team.
     *
     * @return User a viewer without `users.list-all`
     */
    private function teamScopedViewer(): User
    {
        return new User(['team_id' => self::VIEWER_TEAM_ID]);
    }

    /**
     * Build an unsaved viewer with no Team.
     *
     * @return User a viewer with `team_id` set to null
     */
    private function teamlessViewer(): User
    {
        return new User(['team_id' => null]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Scope results to the viewer's Team by default.
     */
    #[Test]
    public function it_scopes_results_to_the_viewers_team_by_default(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var UserFilters $filters */
        $filters = new UserFilters(viewer: $this->teamScopedViewer());

        // Act

        (new UserFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame('users.team_id', $query->getQuery()->wheres[0]['column']);
        $this->assertSame([self::VIEWER_TEAM_ID], $query->getBindings());
    }

    /**
     * Scope to a null Team when the viewer has no Team.
     */
    #[Test]
    public function it_scopes_to_a_null_team_when_the_viewer_has_no_team(): void
    {
        /*
         * `team_id` is a nullable foreign key. A viewer with no Team must
         * still scope to that null value rather than fall through to an
         * unscoped query — otherwise a stray User would see every row.
         */

        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var UserFilters $filters */
        $filters = new UserFilters(viewer: $this->teamlessViewer());

        // Act

        (new UserFilterQuery)->apply($query, $filters);

        // Assert

        /*
         * A bare `where('column', null)` compiles to a `whereNull` clause
         * rather than a bound `= ?` comparison, so this asserts the clause
         * type instead of a bindable value.
         */
        $this->assertSame('Null', $query->getQuery()->wheres[0]['type']);
        $this->assertSame('users.team_id', $query->getQuery()->wheres[0]['column']);
    }

    /**
     * Not scope viewers who list all Teams.
     */
    #[Test]
    public function it_does_not_scope_viewers_who_list_all_teams(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var UserFilters $filters */
        $filters = new UserFilters(viewer: $this->teamScopedViewer(), listsAllTeams: true);

        // Act

        (new UserFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame([], $query->getQuery()->wheres);
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

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var UserFilters $filters */
        $filters = new UserFilters(
            viewer: $this->teamScopedViewer(),
            listsAllTeams: true,
            search: $term,
        );

        // Act

        (new UserFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame([$expectedPattern, $expectedPattern], $query->getBindings());

        $searchGroup = $query->getQuery()->wheres[0]['query']->wheres ?? [];
        $this->assertStringContainsString('ESCAPE', (string) ($searchGroup[0]['sql'] ?? ''));
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
            'plain term is wrapped only' => ['acme', '%acme%'],
            'match-all wildcard is escaped' => ['%', '%\%%'],
            'single-character wildcard is escaped' => ['_', '%\_%'],
            'escape character is doubled' => ['\\', '%\\\\%'],
            'mixed wildcards are escaped' => ['acme%_corp', '%acme\%\_corp%'],
        ];
    }
}
