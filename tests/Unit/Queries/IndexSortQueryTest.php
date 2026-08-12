<?php

declare(strict_types=1);

namespace Tests\Unit\Queries;

use App\DataTransferObjects\IndexSort;
use App\Models\User;
use App\Queries\IndexSortQuery;
use App\Queries\Users\UserQueryConstraints;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the shared IndexSortQuery.
 *
 * Ordering is asserted against the builder's own order list, so these run
 * with no database.
 */
#[CoversClass(IndexSortQuery::class)]
final class IndexSortQueryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Order by an allow-listed column with an id tie-break.
     */
    #[Test]
    public function it_orders_by_a_whitelisted_column_with_an_id_tie_break(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var IndexSort $sort */
        $sort = new IndexSort(column: 'name', direction: 'asc');

        // Act

        (new IndexSortQuery)->apply(
            query: $query,
            sort: $sort,
            allowedSorts: UserQueryConstraints::ALLOWED_SORTS,
            table: 'users',
        );

        // Assert

        $this->assertSame(
            [
                ['column' => 'users.name', 'direction' => 'asc'],
                ['column' => 'users.id', 'direction' => 'desc'],
            ],
            $query->getQuery()->orders,
        );
    }

    /**
     * Order descending when the sort direction is desc.
     */
    #[Test]
    public function it_orders_descending_when_the_sort_direction_is_desc(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var IndexSort $sort */
        $sort = new IndexSort(column: 'created_at', direction: 'desc');

        // Act

        (new IndexSortQuery)->apply(
            query: $query,
            sort: $sort,
            allowedSorts: UserQueryConstraints::ALLOWED_SORTS,
            table: 'users',
        );

        // Assert

        $this->assertSame(
            [
                ['column' => 'users.created_at', 'direction' => 'desc'],
                ['column' => 'users.id', 'direction' => 'desc'],
            ],
            $query->getQuery()->orders,
        );
    }

    /**
     * Omit the tie-break when sorting by the tie-break column.
     */
    #[Test]
    public function it_omits_the_tie_break_when_sorting_by_the_tie_break_column(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var IndexSort $sort */
        $sort = new IndexSort(column: 'id', direction: 'asc');

        // Act

        (new IndexSortQuery)->apply(
            query: $query,
            sort: $sort,
            allowedSorts: ['id'],
            table: 'users',
        );

        // Assert

        $this->assertSame(
            [['column' => 'users.id', 'direction' => 'asc']],
            $query->getQuery()->orders,
        );
    }

    /**
     * Apply no ordering for columns outside the allow list.
     */
    #[Test]
    public function it_applies_no_ordering_for_columns_outside_the_allow_list(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var IndexSort $sort */
        $sort = new IndexSort(column: 'password', direction: 'asc');

        // Act

        (new IndexSortQuery)->apply(
            query: $query,
            sort: $sort,
            allowedSorts: UserQueryConstraints::ALLOWED_SORTS,
            table: 'users',
        );

        // Assert

        $this->assertNull($query->getQuery()->orders);
    }

    /**
     * Reject a table identifier that could carry SQL.
     */
    #[Test]
    public function it_rejects_a_table_identifier_that_could_carry_sql(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        /** @var IndexSort $sort */
        $sort = new IndexSort(column: 'name', direction: 'asc');

        // Act + Assert

        $this->expectException(InvalidArgumentException::class);

        (new IndexSortQuery)->apply(
            query: $query,
            sort: $sort,
            allowedSorts: UserQueryConstraints::ALLOWED_SORTS,
            table: 'users; drop table users',
        );
    }
}
