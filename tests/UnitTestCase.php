<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Base class for unit tests that must not touch the database.
 *
 * Feature tests use {@see TestCase} with {@see Illuminate\Foundation\Testing\RefreshDatabase}.
 * Unit tests extend this class so any accidental query fails the test at teardown.
 */
abstract class UnitTestCase extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Return a query builder's WHERE clauses, each narrowed to an array.
     *
     * Laravel types `$wheres` as `array<int, mixed>`, and the filter tests read
     * named keys off each clause - which level 10 analysis flags on `mixed`.
     *
     * @param  Builder<*> $query the query builder under assertion
     * @return list<array<mixed, mixed>>
     */
    protected function queryWheres(Builder $query): array
    {
        return array_values(array_filter($query->getQuery()->wheres, 'is_array'));
    }

    /**
     * Return the clauses of a nested `where(function ...)` group.
     *
     * @param  Builder<*> $query the query builder under assertion
     * @param  int                       $index the top-level clause index holding the group
     * @return list<array<mixed, mixed>>
     */
    protected function nestedQueryWheres(Builder $query, int $index): array
    {
        $group = $this->queryWheres($query)[$index]['query'] ?? null;

        if (! $group instanceof QueryBuilder) {
            return [];
        }

        return array_values(array_filter($group->wheres, 'is_array'));
    }

    /**
     * Read a WHERE clause's raw SQL fragment as a string.
     *
     * @param  array<mixed, mixed> $clause a clause from {@see queryWheres()}
     * @return string              the SQL fragment, or an empty string when absent
     */
    protected function clauseSql(array $clause): string
    {
        $sql = $clause['sql'] ?? null;

        return is_string($sql) ? $sql : '';
    }

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Fail the test when any database query runs during a unit test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->expectsDatabaseQueryCount(0);
    }
}
