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
            new WebhookDeliveryFilters(event: 'user.created', status: 'failed'),
        );

        // Assert

        /** @var array<int, array<string, mixed>> $wheres */
        $wheres = $query->getQuery()->wheres;

        $this->assertSame(
            ['webhook_endpoint_id', 'event', 'status'],
            array_column($wheres, 'column'),
        );
        $this->assertSame([7, 'user.created', 'failed'], array_column($wheres, 'value'));
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
}
