<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\AuthAuditLogs;

use App\DataTransferObjects\AuthAuditLogs\AuthAuditLogFilters;
use App\Enums\AuthAuditEvent;
use App\Models\AuthAuditLog;
use App\Queries\AuthAuditLogs\AuthAuditLogFilterQuery;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for AuthAuditLogFilterQuery constraints.
 *
 * Constraints are asserted against the builder bindings so these run with no database.
 */
#[CoversClass(AuthAuditLogFilterQuery::class)]
final class AuthAuditLogFilterQueryTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Apply a case-insensitive contains pattern when search is present.
     */
    #[Test]
    public function it_applies_a_contains_pattern_when_search_is_present(): void
    {
        // Arrange

        /** @var Builder<AuthAuditLog> $query */
        $query = AuthAuditLog::query();

        // Act

        (new AuthAuditLogFilterQuery)->apply($query, new AuthAuditLogFilters(search: 'admin@'));

        // Assert

        $this->assertSame(
            [LikePattern::contains('admin@')],
            $query->getBindings(),
        );
    }

    /**
     * Apply an exact event match when event is present.
     */
    #[Test]
    public function it_applies_an_event_constraint_when_event_is_present(): void
    {
        // Arrange

        /** @var Builder<AuthAuditLog> $query */
        $query = AuthAuditLog::query();

        // Act

        (new AuthAuditLogFilterQuery)->apply(
            $query,
            new AuthAuditLogFilters(event: AuthAuditEvent::LoginFailed),
        );

        // Assert

        $this->assertSame(
            [AuthAuditEvent::LoginFailed->value],
            $query->getBindings(),
        );
    }

    /**
     * Apply user and API client id constraints when those filters are present.
     */
    #[Test]
    public function it_applies_user_and_api_client_id_constraints_when_present(): void
    {
        // Arrange

        /** @var Builder<AuthAuditLog> $query */
        $query = AuthAuditLog::query();

        // Act

        (new AuthAuditLogFilterQuery)->apply(
            $query,
            new AuthAuditLogFilters(userId: 7, apiClientId: 3),
        );

        // Assert

        $this->assertSame([7, 3], $query->getBindings());
    }

    /**
     * Leave the builder unchanged when every filter is omitted.
     */
    #[Test]
    public function it_does_not_add_constraints_when_filters_are_omitted(): void
    {
        // Arrange

        /** @var Builder<AuthAuditLog> $query */
        $query = AuthAuditLog::query();

        // Act

        (new AuthAuditLogFilterQuery)->apply($query, new AuthAuditLogFilters);

        // Assert

        $this->assertSame([], $query->getBindings());
        $this->assertSame([], $query->getQuery()->wheres);
    }
}
