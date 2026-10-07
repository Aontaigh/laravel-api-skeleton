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
use App\Support\CommaListRule;
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
use Tests\Concerns\AssertsApiEnvelope;
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
#[CoversClass(CommaListRule::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class AuthAuditLogIndexControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AssertsApiEnvelope;
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and enable strict Eloquent checks for index queries.
     *
     * @return void
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
     *
     * @return void
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

        AuthAuditLog::query()->delete();

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

        AuthAuditLog::query()->delete();

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
     * Filter audit logs by user ID and API client ID.
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
     * must receive it - the query layer must not strip a column that the
     * validation layer permitted.
     */
    #[Test]
    public function it_respects_permission_aware_email_allow_list_on_user_include(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $subject */
        $subject = User::factory()->user()->create(['email' => 'audit@example.com']);
        AuthAuditLog::factory()->for($subject)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?include=user&fields[users]=id,name,email',
        );

        // Assert

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
     * Authorisation Tests
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
        $user = User::factory()->user()->create([
            'email' => 'audit-deny-'.uniqid('', true).'@example.com',
        ]);

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

    /**
     * Filter audit logs by several User IDs in one comma-separated list.
     */
    #[Test]
    public function it_filters_audit_logs_by_several_user_ids(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $first */
        $first = User::factory()->create();
        /** @var User $second */
        $second = User::factory()->create();
        /** @var User $unrelated */
        $unrelated = User::factory()->create();

        AuthAuditLog::factory()->create(['user_id' => $first->id]);
        AuthAuditLog::factory()->create(['user_id' => $second->id]);
        AuthAuditLog::factory()->create(['user_id' => $unrelated->id]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?filter[user_id]='.$first->id.','.$second->id,
        );

        // Assert

        $response->assertOk();
        /** @var list<array{user_id: int}> $rows */
        $rows = $response->json('data');
        $userIds = array_map(static fn (array $row): int => $row['user_id'], $rows);
        sort($userIds);

        $this->assertSame([$first->id, $second->id], $userIds);
    }

    /**
     * A single User ID filter still matches exactly one User, so existing callers are unaffected.
     */
    #[Test]
    public function it_still_filters_by_a_single_user_id(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $first */
        $first = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        AuthAuditLog::factory()->create(['user_id' => $first->id]);
        AuthAuditLog::factory()->create(['user_id' => $other->id]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id]='.$first->id);

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.user_id', $first->id);
    }

    /**
     * Tolerate padding, blank segments, and repeats in the list.
     */
    #[Test]
    public function it_tolerates_padding_blanks_and_repeats_in_the_list(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $first */
        $first = User::factory()->create();
        /** @var User $second */
        $second = User::factory()->create();

        AuthAuditLog::factory()->create(['user_id' => $first->id]);
        AuthAuditLog::factory()->create(['user_id' => $second->id]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?filter[user_id]=%20'.$first->id.',,'.$second->id.','.$first->id,
        );

        // Assert

        $response->assertOk();
        /** @var list<array{user_id: int}> $rows */
        $rows = $response->json('data');
        $userIds = array_map(static fn (array $row): int => $row['user_id'], $rows);
        sort($userIds);

        $this->assertSame([$first->id, $second->id], $userIds);
    }

    /**
     * Combine several filters, each of which is itself a list.
     */
    #[Test]
    public function it_combines_a_list_of_user_ids_with_a_list_of_events(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $first */
        $first = User::factory()->create();
        /** @var User $second */
        $second = User::factory()->create();

        AuthAuditLog::factory()->create(['user_id' => $first->id, 'event' => AuthAuditEvent::Login]);
        AuthAuditLog::factory()->create(['user_id' => $first->id, 'event' => AuthAuditEvent::LoginFailed]);
        AuthAuditLog::factory()->create(['user_id' => $second->id, 'event' => AuthAuditEvent::Login]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/audit-logs?filter[user_id]='.$first->id.','.$second->id
            .'&filter[event]='.urlencode(AuthAuditEvent::Login->value),
        );

        // Assert

        $response->assertOk();
        /** @var list<array{user_id: int}> $rows */
        $rows = $response->json('data');
        $userIds = array_map(static fn (array $row): int => $row['user_id'], $rows);
        sort($userIds);

        $this->assertSame([$first->id, $second->id], $userIds);
    }

    /**
     * Reject a User ID list past the cap instead of truncating it.
     *
     * A truncated list would return a partial answer that looks complete.
     */
    #[Test]
    public function it_rejects_a_user_id_list_past_the_cap(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        $ids = implode(',', range(1, AuthAuditLogQueryConstraints::MAX_FILTER_USER_IDS + 1));

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id]='.$ids);

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_id']);
        $this->assertStringContainsString(
            'At Most '.AuthAuditLogQueryConstraints::MAX_FILTER_USER_IDS,
            $this->firstFilterError($response, 'filter.user_id'),
        );
    }

    /**
     * Accept a User ID list exactly at the cap.
     */
    #[Test]
    public function it_accepts_a_user_id_list_exactly_at_the_cap(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        $ids = implode(',', range(1, AuthAuditLogQueryConstraints::MAX_FILTER_USER_IDS));

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id]='.$ids);

        // Assert

        $response->assertOk();
    }

    /**
     * Reject an event value that is not on the allow-list.
     */
    #[Test]
    public function it_rejects_an_event_outside_the_allow_list(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[event]=Login,NotAnEvent');

        // Assert

        $this->assertApiValidationErrors($response, ['filter.event']);
        $this->assertStringContainsString('NotAnEvent', $this->firstFilterError($response, 'filter.event'));
    }

    /**
     * Reject a User ID that is not numeric rather than silently dropping it.
     */
    #[Test]
    public function it_rejects_a_non_numeric_user_id(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id]=1,abc');

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_id']);
        $this->assertStringContainsString('abc', $this->firstFilterError($response, 'filter.user_id'));
    }

    /**
     * Reject the pluralised sibling key, so a client learns the singular key is the one to use.
     */
    #[Test]
    public function it_rejects_the_pluralised_user_ids_key(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_ids]=1,2');

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_ids']);
        $this->assertStringContainsString('user_ids', $this->firstFilterError($response, 'filter.user_ids'));
    }

    /**
     * Reject a nested operator object, which no surveyed API uses.
     */
    #[Test]
    public function it_rejects_a_nested_operator_object(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id][any_of]=1,2');

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_id']);
        /*
         * A dotted attribute would otherwise humanise into `The filter.user id field must be a
         * string.`, which is sentence case with a trailing period and a name the client never
         * sent. The request's messages() entry names the key literally instead.
         */
        $this->assertSame(
            'The filter.user_id Value Must Be A Comma-Separated List',
            $this->firstFilterError($response, 'filter.user_id'),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Read the first validation message for a filter key from the error envelope.
     *
     * Laravel keys a validation error against the attribute as written, so a dotted rule key
     * stays a literal `filter.user_id` in the array rather than nesting. Reading it through
     * `json('meta.errors.…')` would therefore find nothing, which is why this indexes the
     * decoded array directly.
     *
     * @param  TestResponse<JsonResponse> $response the 422 response
     * @param  string                     $key      the `filter[…]` key, for example `filter.user_id`
     * @return string                     the first message, or an empty string when absent
     */
    private function firstFilterError(TestResponse $response, string $key): string
    {
        /** @var array<string, list<string>> $errors */
        $errors = $response->json('meta.errors') ?? [];

        return $errors[$key][0] ?? '';
    }

    /**
     * A rejected value carries the supported filter keys, so the caller can self-correct.
     */
    #[Test]
    public function it_returns_the_allowed_filters_when_a_value_is_rejected(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id]=1,abc');

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_id']);
        $this->assertSame(
            ['api_client_id', 'event', 'search', 'user_id'],
            $response->json('meta.allowed.filter'),
        );
    }

    /**
     * A nested value is refused with copy that names the exact key the client sent.
     */
    #[Test]
    public function it_does_not_humanise_a_dotted_filter_key(): void
    {
        // Arrange

        AuthAuditLog::query()->delete();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs?filter[user_id][any_of]=1,2');

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_id']);

        $message = $this->firstFilterError($response, 'filter.user_id');

        $this->assertStringNotContainsString('filter.user id', $message);
        $this->assertStringEndsNotWith('.', $message);
        $this->assertStringContainsString('filter.user_id', $message);
    }
}
