<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Http\Controllers\Webhooks\WebhookDeliveryIndexController;
use App\Http\Requests\Webhooks\WebhookDeliveryIndexRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Webhooks\WebhookDeliveryFilterQuery;
use App\Queries\Webhooks\WebhookDeliveryQueryConstraints;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the endpoint delivery history index.
 */
#[CoversClass(WebhookDeliveryIndexController::class)]
#[CoversClass(WebhookDeliveryIndexRequest::class)]
#[CoversClass(WebhookDeliveryResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(WebhookDeliveryFilterQuery::class)]
#[CoversClass(WebhookDeliveryQueryConstraints::class)]
#[CoversClass(ApiResponse::class)]
final class WebhookDeliveryIndexControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed roles and permissions.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Index Tests
     * -----------
     */

    /**
     * Return only the route-bound endpoint's deliveries.
     */
    #[Test]
    public function it_scopes_deliveries_to_the_endpoint(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookEndpoint $otherEndpoint */
        $otherEndpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();
        WebhookDelivery::factory()->for($otherEndpoint, 'endpoint')->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/webhook-endpoints/{$endpoint->id}/deliveries");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Webhook Deliveries Retrieved Successfully');
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Filter deliveries by event and status.
     */
    #[Test]
    public function it_filters_by_event_and_status(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Failed,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/webhook-endpoints/{$endpoint->id}/deliveries?filter[event]=user.created&filter[status]=failed",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'failed');
    }

    /**
     * Support sparse fieldsets including the delivery UUID.
     */
    #[Test]
    public function it_supports_sparse_fieldsets(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/webhook-endpoints/{$endpoint->id}/deliveries?fields[webhook_deliveries]=id,uuid,event,status",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.attempts');
    }

    /**
     * Accept the synthetic ping event as a filter value.
     */
    #[Test]
    public function it_accepts_the_ping_event_filter(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => WebhookEvent::PING,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/webhook-endpoints/{$endpoint->id}/deliveries?filter[event]=webhook.ping",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Reject unknown filter values.
     */
    #[Test]
    #[DataProvider('invalidFilterProvider')]
    public function it_rejects_invalid_filter_values(string $query): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/webhook-endpoints/{$endpoint->id}/deliveries?{$query}",
        );

        // Assert

        $response->assertUnprocessable();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Query strings carrying hostile filter values the index must reject.
     *
     * @return array<string, array{0: string}> case name mapped to [query]
     */
    public static function invalidFilterProvider(): array
    {
        return [
            'unknown event' => ['filter[event]=user.exploded'],
            'unknown status' => ['filter[status]=quantum'],
            'unknown event inside a list' => ['filter[event]=user.created,user.exploded'],
            'unknown status inside a list' => ['filter[status]=failed,quantum'],
            'pluralised sibling key' => ['filter[events]=user.created'],
            'nested operator object' => ['filter[event][any_of]=user.created'],
        ];
    }

    /**
     * Filter deliveries by several events in one comma-separated list.
     */
    #[Test]
    public function it_filters_deliveries_by_several_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.suspended',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'order.paid',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/webhook-endpoints/'.$endpoint->id.'/deliveries?filter[event]=user.created,user.suspended',
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        /** @var list<array{event: string}> $rows */
        $rows = $response->json('data');
        $events = array_map(static fn (array $row): string => $row['event'], $rows);

        $this->assertContains('user.created', $events);
        $this->assertContains('user.suspended', $events);
        $this->assertNotContains('order.paid', $events);
    }

    /**
     * Filter deliveries by several statuses in one comma-separated list.
     */
    #[Test]
    public function it_filters_deliveries_by_several_statuses(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Failed,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Pending,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/webhook-endpoints/'.$endpoint->id.'/deliveries?filter[status]=delivered,failed',
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        /** @var list<array{status: string}> $rows */
        $rows = $response->json('data');
        $statuses = array_map(static fn (array $row): string => $row['status'], $rows);

        $this->assertContains('delivered', $statuses);
        $this->assertContains('failed', $statuses);
        $this->assertNotContains('pending', $statuses);
    }

    /**
     * Combine an event list with a status list, each filtered on its own terms.
     */
    #[Test]
    public function it_combines_an_event_list_with_a_status_list(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Failed,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.suspended',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/webhook-endpoints/'.$endpoint->id.'/deliveries?filter[event]=user.created,user.suspended&filter[status]=delivered',
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /**
     * Tolerate padding, blank segments, and repeats in an event list.
     */
    #[Test]
    public function it_tolerates_padding_blanks_and_repeats_in_the_event_list(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.created',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);
        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'event' => 'user.suspended',
            'status' => WebhookDeliveryStatus::Delivered,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/webhook-endpoints/'.$endpoint->id.'/deliveries?filter[event]=user.created,,user.suspended,user.created',
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Arrange

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson("/api/webhook-endpoints/{$endpoint->id}/deliveries");

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny regular Users.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/webhook-endpoints/{$endpoint->id}/deliveries");

        // Assert

        $response->assertForbidden();
    }
}
