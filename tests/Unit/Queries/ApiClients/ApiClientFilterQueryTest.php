<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\ApiClients;

use App\DataTransferObjects\ApiClients\ApiClientFilters;
use App\Models\ApiClient;
use App\Queries\ApiClients\ApiClientFilterQuery;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for ApiClientFilterQuery search constraints.
 *
 * Constraints are asserted against the builder bindings so these run with no database.
 */
#[CoversClass(ApiClientFilterQuery::class)]
final class ApiClientFilterQueryTest extends UnitTestCase
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

        /** @var Builder<ApiClient> $query */
        $query = ApiClient::query();

        // Act

        (new ApiClientFilterQuery)->apply($query, new ApiClientFilters(search: 'Billing'));

        // Assert

        $this->assertSame(
            [LikePattern::contains('Billing')],
            $query->getBindings(),
        );
    }

    /**
     * Leave the builder unchanged when search is omitted.
     */
    #[Test]
    public function it_does_not_add_constraints_when_search_is_omitted(): void
    {
        // Arrange

        /** @var Builder<ApiClient> $query */
        $query = ApiClient::query();

        // Act

        (new ApiClientFilterQuery)->apply($query, new ApiClientFilters);

        // Assert

        $this->assertSame([], $query->getBindings());
        $this->assertSame([], $query->getQuery()->wheres);
    }
}
