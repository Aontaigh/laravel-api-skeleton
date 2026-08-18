<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Sessions;

use App\DataTransferObjects\Sessions\SessionFilters;
use App\Models\User;
use App\Models\WebSession;
use App\Queries\Sessions\SessionFilterQuery;
use App\Queries\Sessions\SessionQueryConstraints;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for SessionFilterQuery row scoping and filters.
 *
 * Constraints are asserted against the builder's own where list and bindings,
 * so these run with no database.
 */
#[CoversClass(SessionFilterQuery::class)]
final class SessionFilterQueryTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The authenticated viewer's ID. */
    private const VIEWER_ID = 42;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build an unsaved viewer for row-scoping assertions.
     */
    private function viewer(): User
    {
        $viewer = new User;
        $viewer->id = self::VIEWER_ID;

        return $viewer;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Scope Web Sessions to the viewer when they lack `sessions.list-all`.
     */
    #[Test]
    public function it_scopes_sessions_to_the_viewer(): void
    {
        // Arrange

        /** @var Builder<WebSession> $query */
        $query = WebSession::query();

        $filters = new SessionFilters(viewer: $this->viewer());

        // Act

        (new SessionFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame(
            SessionQueryConstraints::TABLE.'.revoked_at',
            $query->getQuery()->wheres[0]['column'],
        );
        $this->assertSame(
            SessionQueryConstraints::TABLE.'.user_id',
            $query->getQuery()->wheres[1]['column'],
        );
        $this->assertSame([self::VIEWER_ID], $query->getBindings());
    }

    /**
     * Omit row scoping when the viewer may list every User's sessions.
     */
    #[Test]
    public function it_omits_row_scoping_when_the_viewer_lists_all_users(): void
    {
        // Arrange

        /** @var Builder<WebSession> $query */
        $query = WebSession::query();

        $filters = new SessionFilters(
            viewer: $this->viewer(),
            listsAllUsers: true,
        );

        // Act

        (new SessionFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertCount(1, $query->getQuery()->wheres);
        $this->assertSame(
            SessionQueryConstraints::TABLE.'.revoked_at',
            $query->getQuery()->wheres[0]['column'],
        );
    }

    /**
     * Apply an owner filter for admin viewers.
     */
    #[Test]
    public function it_applies_the_user_id_filter_for_admin_viewers(): void
    {
        // Arrange

        /** @var Builder<WebSession> $query */
        $query = WebSession::query();

        $filters = new SessionFilters(
            viewer: $this->viewer(),
            listsAllUsers: true,
            userId: 99,
        );

        // Act

        (new SessionFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame(
            SessionQueryConstraints::TABLE.'.user_id',
            $query->getQuery()->wheres[1]['column'],
        );
        $this->assertSame([99], array_slice($query->getBindings(), -1));
    }

    /**
     * Escape LIKE wildcards in the search term.
     */
    #[Test]
    #[DataProvider('searchTermProvider')]
    public function it_escapes_like_wildcards_in_the_search_term(string $term, string $expectedPattern): void
    {
        // Arrange

        /** @var Builder<WebSession> $query */
        $query = WebSession::query();

        $filters = new SessionFilters(
            viewer: $this->viewer(),
            search: $term,
        );

        // Act

        (new SessionFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame([self::VIEWER_ID, $expectedPattern, $expectedPattern, $expectedPattern], $query->getBindings());

        $this->assertStringContainsString(
            'ESCAPE',
            (string) ($query->getQuery()->wheres[2]['query']->wheres[0]['sql'] ?? ''),
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
            'plain term is wrapped only' => ['chrome', '%chrome%'],
            'match-all wildcard is escaped' => ['%', '%\\%%'],
            'single-character wildcard is escaped' => ['_', '%\\_%'],
            'escape character is doubled' => ['\\\\', '%\\\\\\\\%'],
            'mixed wildcards are escaped' => ['cli%_token', '%cli\%\_token%'],
        ];
    }
}
