<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Webhooks;

use App\DataTransferObjects\Webhooks\WebhookEndpointFilters;
use App\Models\WebhookEndpoint;
use App\Queries\Webhooks\WebhookEndpointFilterQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for webhook endpoint filter composition (no database).
 */
#[CoversClass(WebhookEndpointFilterQuery::class)]
final class WebhookEndpointFilterQueryTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Search name and URL columns with an escaped LIKE predicate.
     */
    #[Test]
    public function it_searches_name_and_url_columns(): void
    {
        // Arrange

        $query = WebhookEndpoint::query();

        // Act

        (new WebhookEndpointFilterQuery)->apply($query, new WebhookEndpointFilters(search: 'bill'));

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertCount(1, $wheres);
        $this->assertSame('Nested', $wheres[0]['type']);
    }

    /**
     * Apply no constraints when no search term is given.
     */
    #[Test]
    public function it_applies_no_constraints_without_a_search_term(): void
    {
        // Arrange

        $query = WebhookEndpoint::query();

        // Act

        (new WebhookEndpointFilterQuery)->apply($query, new WebhookEndpointFilters);

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertSame([], $wheres);
    }
}
