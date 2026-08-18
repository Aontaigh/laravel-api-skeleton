<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Sessions;

use App\DataTransferObjects\IndexSort;
use App\DataTransferObjects\Sessions\SessionFilters;
use App\Http\Controllers\Sessions\SessionIndexController;
use App\Http\Requests\Sessions\SessionIndexRequest;
use App\Http\Resources\WebSessionResource;
use App\Models\User;
use App\Models\WebSession;
use App\Policies\WebSessionPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Sessions\SessionFilterQuery;
use App\Queries\Sessions\SessionIncludeQuery;
use App\Queries\Sessions\SessionQueryConstraints;
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
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests for the Web Session Index endpoint.
 */
#[CoversClass(SessionIndexController::class)]
#[CoversClass(SessionIndexRequest::class)]
#[CoversClass(WebSessionResource::class)]
#[CoversClass(WebSessionPolicy::class)]
#[CoversClass(SessionFilterQuery::class)]
#[CoversClass(SessionIncludeQuery::class)]
#[CoversClass(SessionQueryConstraints::class)]
#[CoversClass(SessionFilters::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(IndexSort::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class SessionIndexControllerTest extends TestCase
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
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var User a permissioned viewer with `sessions.list-own` */
    private User $viewer;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and create the shared viewer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->viewer = User::factory()->user()->create();
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
     * Listing and Scoping Tests
     * -------------------------
     */

    /**
     * Return only the viewer's own active Web Sessions.
     */
    #[Test]
    public function it_returns_only_the_viewers_own_active_sessions(): void
    {
        // Arrange

        $ownSession = WebSession::factory()->for($this->viewer)->create();
        WebSession::factory()->create();
        WebSession::factory()->for($this->viewer)->revoked()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Sessions Retrieved Successfully');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownSession->id);
        $response->assertJsonPath('meta.pagination.total', 1);
    }

    /**
     * Never expose the Laravel session id in the API payload.
     */
    #[Test]
    public function it_never_exposes_the_laravel_session_id(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create([
            'session_id' => 'secret-session-id',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $sessionPayload */
        $sessionPayload = $response->json('data.0');

        $this->assertArrayNotHasKey('session_id', $sessionPayload);
    }

    /**
     * Filter Web Sessions by the search term.
     */
    #[Test]
    public function it_filters_by_search_term(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create([
            'device_name' => 'CLI Session',
        ]);
        WebSession::factory()->for($this->viewer)->create([
            'device_name' => 'Browser Session',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions?filter[search]=CLI');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.device_name', 'CLI Session');
    }

    /**
     * List Web Sessions across every User when the viewer holds `sessions.list-all`.
     */
    #[Test]
    public function it_lists_sessions_across_every_user_when_the_viewer_holds_list_all(): void
    {
        // Arrange

        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->user()->create();

        WebSession::factory()->for($this->viewer)->create();
        WebSession::factory()->for($otherUser)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/sessions');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /**
     * Filter Web Sessions by `filter[user_id]` for admin viewers.
     */
    #[Test]
    public function it_filters_sessions_by_user_id_for_admin_viewers(): void
    {
        // Arrange

        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->user()->create();
        $targetSession = WebSession::factory()->for($otherUser)->create();
        WebSession::factory()->for($this->viewer)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/sessions?filter[user_id]={$otherUser->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $targetSession->id);
    }

    /**
     * Reject `filter[user_id]` for callers without `sessions.list-all`.
     */
    #[Test]
    public function it_rejects_user_id_filter_for_callers_without_list_all(): void
    {
        // Arrange

        $otherUser = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson("/api/sessions?filter[user_id]={$otherUser->id}");

        // Assert

        $this->assertApiValidationErrors($response, ['filter.user_id']);
    }

    /*
     * Pagination Tests
     * ----------------
     */

    /**
     * Return a later page with accurate pagination meta.
     */
    #[Test]
    public function it_returns_a_later_page_with_accurate_pagination_meta(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create(['device_name' => 'Alpha']);
        WebSession::factory()->for($this->viewer)->create(['device_name' => 'Bravo']);
        WebSession::factory()->for($this->viewer)->create(['device_name' => 'Charlie']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions?per_page=2&page=2&sort=device_name');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.device_name', 'Charlie');
        $response->assertJsonPath('meta.pagination.current_page', 2);
        $response->assertJsonPath('meta.pagination.per_page', 2);
        $response->assertJsonPath('meta.pagination.total', 3);
        $response->assertJsonPath('meta.pagination.last_page', 2);
    }

    /*
     * Sorting Tests
     * -------------
     */

    /**
     * Apply the documented default sort when sort is omitted.
     */
    #[Test]
    public function it_applies_the_documented_default_sort_when_sort_is_omitted(): void
    {
        // Arrange

        $older = WebSession::factory()->for($this->viewer)->create([
            'last_activity_at' => now()->subHour(),
        ]);
        $newer = WebSession::factory()->for($this->viewer)->create([
            'last_activity_at' => now(),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
    }

    /**
     * Sort ascending by device name when requested.
     */
    #[Test]
    public function it_sorts_ascending_by_device_name_when_requested(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create(['device_name' => 'Charlie']);
        WebSession::factory()->for($this->viewer)->create(['device_name' => 'Alpha']);
        WebSession::factory()->for($this->viewer)->create(['device_name' => 'Bravo']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions?sort=device_name');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.*.device_name', ['Alpha', 'Bravo', 'Charlie']);
    }

    /*
     * Include Tests
     * -------------
     */

    /**
     * Eager-load the nested User relation when requested.
     */
    #[Test]
    public function it_eager_loads_the_user_relation_when_requested(): void
    {
        // Arrange

        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->user()->create(['name' => 'Included User']);

        WebSession::factory()->for($otherUser)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/sessions?include=user&fields[users]=id,name');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.user.id', $otherUser->id);
        $response->assertJsonPath('data.0.user.name', 'Included User');
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny service accounts from listing Web Sessions.
     */
    #[Test]
    public function it_denies_service_accounts(): void
    {
        // Arrange

        $service = User::factory()->service()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($service)->getJson('/api/sessions');

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_requires_authentication(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/sessions');

        // Assert

        $response->assertUnauthorized();
    }

    /**
     * Deny viewers without the list-own permission.
     */
    #[Test]
    public function it_denies_viewers_without_the_list_own_permission(): void
    {
        // Arrange

        /** @var User $roleless */
        $roleless = User::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($roleless)->getJson('/api/sessions');

        // Assert

        $response->assertForbidden();
    }

    /*
     * Sparse Fieldset Tests
     * ---------------------
     */

    /**
     * Return only requested Web Session columns in sparse fieldsets.
     */
    #[Test]
    public function it_returns_only_requested_session_columns_in_sparse_fieldsets(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create([
            'device_name' => 'Sparse Session',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions?fields[sessions]=id,device_name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $session */
        $session = $response->json('data.0');

        $this->assertSame(['id', 'device_name'], array_keys($session));
    }

    /**
     * Omit `user_id` for callers without `sessions.list-all` even when the
     * default projection selects every column.
     */
    #[Test]
    public function it_omits_user_id_for_callers_without_list_all(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create([
            'ip_address' => '203.0.113.10',
            'user_agent' => 'PHPUnit Agent',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $session */
        $session = $response->json('data.0');

        $this->assertArrayNotHasKey('user_id', $session);
        $this->assertSame('203.0.113.10', $session['ip_address']);
        $this->assertSame('PHPUnit Agent', $session['user_agent']);
    }

    /**
     * Expose cross-user session telemetry when the viewer holds `sessions.list-all`.
     */
    #[Test]
    public function it_exposes_cross_user_session_telemetry_for_list_all_callers(): void
    {
        // Arrange

        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->user()->create();

        WebSession::factory()->for($otherUser)->create([
            'ip_address' => '198.51.100.4',
            'user_agent' => 'Other Browser',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/sessions');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.user_id', $otherUser->id);
        $response->assertJsonPath('data.0.ip_address', '198.51.100.4');
        $response->assertJsonPath('data.0.user_agent', 'Other Browser');
    }

    /**
     * Include the computed `is_current` flag by default.
     */
    #[Test]
    public function it_includes_is_current_by_default(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $session */
        $session = $response->json('data.0');

        $this->assertArrayHasKey('is_current', $session);
        $this->assertFalse($response->json('data.0.is_current'));
    }

    /**
     * Omit the computed `is_current` flag when `fields[sessions]` excludes it.
     */
    #[Test]
    public function it_omits_is_current_when_fields_exclude_it(): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/sessions?fields[sessions]=id,device_name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $session */
        $session = $response->json('data.0');

        $this->assertArrayNotHasKey('is_current', $session);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject out-of-allow-list query params.
     */
    #[Test]
    #[DataProvider('invalidQueryProvider')]
    public function it_rejects_out_of_allow_list_query_params(string $queryString, string $expectedErrorKey): void
    {
        // Arrange

        WebSession::factory()->for($this->viewer)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson("/api/sessions?{$queryString}");

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
     * Hostile and out-of-allow-list Query Params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=session_id', 'sort'],
            'unknown filter key' => ['filter[remember_me]=1', 'filter.remember_me'],
            'unknown sparse field' => ['fields[sessions]=id,session_id', 'fields.sessions'],
            'user id sparse field without list all' => ['fields[sessions]=id,user_id', 'fields.sessions'],
            'unknown fields resource' => ['fields[tokens]=id', 'fields.tokens'],
            'unknown include' => ['include=team', 'include'],
            'array-shaped sort' => ['sort[]=device_name', 'sort'],
            'array-shaped include' => ['include[]=user', 'include'],
            'array-shaped sparse field' => ['fields[sessions][]=id', 'fields.sessions'],
            'scalar filter container' => ['filter=name', 'filter'],
            'scalar fields container' => ['fields=name', 'fields'],
            'page size above the hard maximum' => [
                'per_page='.(SessionQueryConstraints::MAX_PER_PAGE + 1),
                'per_page',
            ],
            'page size below one' => ['per_page=0', 'per_page'],
            'page below one' => ['page=0', 'page'],
        ];
    }
}
