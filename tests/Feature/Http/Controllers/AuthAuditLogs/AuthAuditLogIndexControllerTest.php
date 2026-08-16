<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AuthAuditLogs;

use App\DataTransferObjects\AuthAuditLogs\AuthAuditLogFilters;
use App\DataTransferObjects\IndexSort;
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\AuthAuditLogs\AuthAuditLogIndexController;
use App\Http\Requests\AuthAuditLogs\AuthAuditLogIndexRequest;
use App\Http\Resources\AuthAuditLogResource;
use App\Models\ApiClient;
use App\Models\AuthAuditLog;
use App\Models\User;
use App\Policies\AuthAuditLogPolicy;
use App\Queries\AuthAuditLogs\AuthAuditLogFilterQuery;
use App\Queries\AuthAuditLogs\AuthAuditLogIncludeQuery;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use App\Support\CommaSeparatedList;
use App\Support\LikePattern;
use App\Support\QualifiedColumn;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the auth audit log index.
 */
#[CoversClass(AuthAuditLogIndexController::class)]
#[CoversClass(AuthAuditLogIndexRequest::class)]
#[CoversClass(AuthAuditLogResource::class)]
#[CoversClass(AuthAuditLogPolicy::class)]
#[CoversClass(AuthAuditLogFilterQuery::class)]
#[CoversClass(AuthAuditLogIncludeQuery::class)]
#[CoversClass(AuthAuditLogQueryConstraints::class)]
#[CoversClass(AuthAuditLogFilters::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(IndexSort::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class AuthAuditLogIndexControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and enable strict Eloquent checks for index queries.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Restore the global strict-mode flags so they do not leak into other suites.
     */
    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Listing Tests
     * -------------
     */

    /**
     * Return every auth audit log row for an admin caller.
     */
    #[Test]
    public function it_lists_auth_audit_logs_for_admins(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        AuthAuditLog::factory()->count(2)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Auth Audit Logs Retrieved Successfully');
        $response->assertJsonCount(2, 'data');
    }

    /**
     * Filter audit logs by partial email search.
     */
    #[Test]
    public function it_filters_audit_logs_by_search_term(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        AuthAuditLog::factory()->create(['email' => 'admin@example.com']);
        AuthAuditLog::factory()->create(['email' => 'other@example.com']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[search]=admin@');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'admin@example.com');
    }

    /**
     * Filter audit logs by event type.
     */
    #[Test]
    public function it_filters_audit_logs_by_event(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        AuthAuditLog::factory()->create(['event' => AuthAuditEvent::Login]);
        AuthAuditLog::factory()->create(['event' => AuthAuditEvent::LoginFailed]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?filter[event]='.urlencode(AuthAuditEvent::LoginFailed->value),
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.event', AuthAuditEvent::LoginFailed->value);
    }

    /**
     * Filter audit logs by user id and API client id.
     */
    #[Test]
    public function it_filters_audit_logs_by_user_id_and_api_client_id(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $targetUser */
        $targetUser = User::factory()->user()->create();
        $client = ApiClient::factory()->create();

        AuthAuditLog::factory()->for($targetUser)->create();
        AuthAuditLog::factory()->create(['api_client_id' => $client->id, 'user_id' => null]);
        AuthAuditLog::factory()->for($targetUser)->create([
            'api_client_id' => $client->id,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/audit-logs?filter[user_id]={$targetUser->id}&filter[api_client_id]={$client->id}",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.user_id', $targetUser->id);
        $response->assertJsonPath('data.0.api_client_id', $client->id);
    }

    /**
     * Eager-load the related user when include=user is requested.
     */
    #[Test]
    public function it_includes_the_related_user_when_requested(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $subject */
        $subject = User::factory()->user()->create(['name' => 'Audit Subject']);
        AuthAuditLog::factory()->for($subject)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?include=user&fields[users]=id,name',
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.user.id', $subject->id);
        $response->assertJsonPath('data.0.user.name', 'Audit Subject');
        $response->assertJsonMissingPath('data.0.user.email');
    }

    /**
     * Respect the permission-aware email allow-list on nested user includes.
     *
     * An admin with `users.view-email` who asks for `fields[users]=email`
     * must receive it — the query layer must not strip a column that the
     * validation layer permitted.
     */
    #[Test]
    public function it_respects_permission_aware_email_allow_list_on_user_include(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $subject */
        $subject = User::factory()->user()->create(['email' => 'audit@example.com']);
        AuthAuditLog::factory()->for($subject)->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?include=user&fields[users]=id,name,email',
        );

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('data.0.user.email', 'audit@example.com');
    }

    /**
     * Apply the documented default sort when sort is omitted.
     */
    #[Test]
    public function it_applies_the_documented_default_sort_when_sort_is_omitted(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $older = AuthAuditLog::factory()->create();
        $newer = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs');

        // Assert

        $response->assertOk();
        $this->assertGreaterThan($older->id, $newer->id);
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
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
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/audit-logs');

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny managers without audit log list permission.
     */
    #[Test]
    public function it_denies_managers_without_audit_log_list_permission(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson('/api/audit-logs');

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny regular users without the Admin role.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/audit-logs');

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny service accounts.
     */
    #[Test]
    public function it_denies_service_accounts(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->getJson('/api/audit-logs');

        // Assert

        $response->assertForbidden();
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject hostile and out-of-allow-list query params.
     */
    #[Test]
    #[DataProvider('invalidQueryProvider')]
    public function it_rejects_invalid_query_params(string $queryString, string $expectedErrorKey): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/audit-logs?{$queryString}");

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Hostile and out-of-allow-list query params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=token', 'sort'],
            'unsupported include' => ['include=team', 'include'],
            'unknown filter key' => ['filter[is_active]=1', 'filter.is_active'],
            'invalid event filter' => ['filter[event]=Not Real', 'filter.event'],
            'unknown sparse field' => ['fields[auth_audit_logs]=id,secret', 'fields.auth_audit_logs'],
            'unknown fields resource' => ['fields[teams]=id', 'fields.teams'],
            'array-shaped sort' => ['sort[]=id', 'sort'],
            'array-shaped sparse field' => ['fields[auth_audit_logs][]=id', 'fields.auth_audit_logs'],
            'scalar filter container' => ['filter=name', 'filter'],
            'scalar fields container' => ['fields=name', 'fields'],
            'page size above the hard maximum' => [
                'per_page='.(AuthAuditLogQueryConstraints::MAX_PER_PAGE + 1),
                'per_page',
            ],
        ];
    }
}
