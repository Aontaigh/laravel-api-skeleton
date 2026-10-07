<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Webhooks;

use App\DataTransferObjects\Webhooks\WebhookDeliveryFilters;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Queries\Webhooks\WebhookDeliveryFilterQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for delivery filter composition (no database).
 */
#[CoversClass(WebhookDeliveryFilterQuery::class)]
final class WebhookDeliveryFilterQueryTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to the endpoint and apply event and status filters.
     */
    #[Test]
    public function it_scopes_to_the_endpoint_and_applies_filters(): void
    {
        // Arrange

        $query = WebhookDelivery::query();

        $endpoint = new WebhookEndpoint;
        $endpoint->id = 7;

        // Act

        (new WebhookDeliveryFilterQuery)->apply(
            $query,
            $endpoint,
            new WebhookDeliveryFilters(events: ['user.created'], statuses: ['failed']),
        );

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertSame(
            ['webhook_endpoint_id', 'event', 'status'],
            array_column($wheres, 'column'),
        );
        $this->assertSame(7, $wheres[0]['value']);
        $this->assertSame(['user.created'], $wheres[1]['values']);
        $this->assertSame(['failed'], $wheres[2]['values']);
    }

    /**
     * Scope to the endpoint with no filters.
     */
    #[Test]
    public function it_scopes_to_the_endpoint_without_filters(): void
    {
        // Arrange

        $query = WebhookDelivery::query();

        $endpoint = new WebhookEndpoint;
        $endpoint->id = 7;

        // Act

        (new WebhookDeliveryFilterQuery)->apply($query, $endpoint, new WebhookDeliveryFilters);

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertSame(['webhook_endpoint_id'], array_column($wheres, 'column'));
    }

    /**
     * Bind a multi-value list to a single whereIn rather than one clause per value.
     */
    #[Test]
    public function it_binds_a_list_to_one_where_in_clause(): void
    {
        // Arrange

        $query = WebhookDelivery::query();

        $endpoint = new WebhookEndpoint;
        $endpoint->id = 7;

        // Act

        (new WebhookDeliveryFilterQuery)->apply(
            $query,
            $endpoint,
            new WebhookDeliveryFilters(events: ['user.created', 'user.suspended']),
        );

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertSame(
            ['webhook_endpoint_id', 'event'],
            array_column($wheres, 'column'),
        );
        $this->assertSame('In', $wheres[1]['type']);
        $this->assertSame(['user.created', 'user.suspended'], $wheres[1]['values']);
    }

    /**
     * An empty list adds no clause, which is what keeps an absent filter unconstrained.
     */
    #[Test]
    public function it_adds_no_filter_clause_for_an_empty_list(): void
    {
        // Arrange

        $query = WebhookDelivery::query();

        $endpoint = new WebhookEndpoint;
        $endpoint->id = 7;

        // Act

        (new WebhookDeliveryFilterQuery)->apply($query, $endpoint, new WebhookDeliveryFilters);

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertSame(['webhook_endpoint_id'], array_column($wheres, 'column'));
    }
}
