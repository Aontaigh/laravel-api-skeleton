<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\DataTransferObjects\IndexSort;
use App\DataTransferObjects\Users\UserFilters;
use App\Enums\RoleName;
use App\Http\Controllers\Users\UserIndexController;
use App\Http\Requests\Users\UserIndexRequest;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Users\UserFilterQuery;
use App\Queries\Users\UserIncludeQuery;
use App\Queries\Users\UserQueryConstraints;
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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the User Index Endpoint.
 *
 * Every class the endpoint composes is listed so coverage is recorded against
 * the whole request path, not just the controller. Without this the Request,
 * Resources, Policy, and Query classes all report as uncovered.
 */
#[CoversClass(UserIndexController::class)]
#[CoversClass(UserIndexRequest::class)]
#[CoversClass(UserResource::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(UserFilterQuery::class)]
#[CoversClass(UserIncludeQuery::class)]
#[CoversClass(UserQueryConstraints::class)]
#[CoversClass(UserFilters::class)]
#[CoversClass(IndexSort::class)]
#[CoversClass(User::class)]
#[CoversClass(Team::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class UserIndexControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var Team the Team the viewer belongs to */
    private Team $team;

    /** @var User a `manager` viewer: `users.list` but not `users.list-all` or `users.view-email` */
    private User $viewer;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Create the Team and authenticated viewer shared by every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * `php-performance` asks for preventLazyLoading() outside production, and
         * preventAccessingMissingAttributes() is its counterpart for this endpoint:
         * it turns a sparse `select()` into a hard failure the moment a Resource
         * reads a column it did not request. Together they make the include tests
         * prove eager loading really happened, and the sparse fieldset tests prove
         * the Resources are safe under `Model::shouldBeStrict()`.
         */
        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->team = Team::factory()->create();

        $this->viewer = User::factory()->for($this->team)->manager()->create([
            'name' => 'Viewer Manager',
            'email' => 'viewer@example.com',
        ]);
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
     * Listing and Scoping Tests
     * -------------------------
     */

    /**
     * Return Users scoped to the viewer's Team.
     */
    #[Test]
    public function it_returns_users_scoped_to_the_viewers_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        User::factory()->for($this->team)->create(['name' => 'Team Member']);
        User::factory()->for($otherTeam)->create(['name' => 'Other Team Member']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Users Retrieved Successfully');
        $response->assertJsonCount(2, 'data');

        $names = (array) $response->json('data.*.name');

        $this->assertContains('Team Member', $names);
        $this->assertNotContains('Other Team Member', $names);
    }

    /**
     * List Users across every Team when the viewer holds the list-all permission.
     */
    #[Test]
    public function it_lists_users_across_every_team_when_the_viewer_holds_list_all(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create(['name' => 'Admin Viewer']);

        User::factory()->for($otherTeam)->create(['name' => 'Cross Team Member']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users');

        // Assert

        $response->assertOk();
        $this->assertContains('Cross Team Member', (array) $response->json('data.*.name'));
    }

    /**
     * Filter Users by the search term.
     */
    #[Test]
    public function it_filters_by_search_term(): void
    {
        // Arrange

        User::factory()->for($this->team)->create(['name' => 'Acme One']);
        User::factory()->for($this->team)->create(['name' => 'Acme Two']);
        User::factory()->for($this->team)->create(['name' => 'Beta User']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?filter[search]=Acme');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Acme One');
        $response->assertJsonPath('data.1.name', 'Acme Two');
    }

    /**
     * Trim whitespace from the search term before filtering.
     */
    #[Test]
    public function it_trims_whitespace_from_the_search_term(): void
    {
        // Arrange

        User::factory()->for($this->team)->create(['name' => 'Acme One']);
        User::factory()->for($this->team)->create(['name' => 'Beta User']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            '/api/users?filter[search]='.rawurlencode('  Acme  '),
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Acme One');
    }

    /**
     * Ignore a blank search term so every User is returned.
     */
    #[Test]
    public function it_ignores_a_blank_search_term(): void
    {
        // Arrange

        User::factory()->for($this->team)->create(['name' => 'Acme One']);
        User::factory()->for($this->team)->create(['name' => 'Beta User']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            '/api/users?filter[search]='.rawurlencode('   '),
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
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

        User::factory()->for($this->team)->create(['name' => 'Alpha']);
        User::factory()->for($this->team)->create(['name' => 'Bravo']);
        User::factory()->for($this->team)->create(['name' => 'Charlie']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?per_page=2&page=2');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.*.name', ['Bravo', 'Charlie']);
        $response->assertJsonPath('meta.pagination.current_page', 2);
        $response->assertJsonPath('meta.pagination.per_page', 2);
        $response->assertJsonPath('meta.pagination.total', 4);
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

        $charlie = User::factory()->for($this->team)->create(['name' => 'Charlie']);
        $alpha = User::factory()->for($this->team)->create(['name' => 'Alpha']);
        $bravo = User::factory()->for($this->team)->create(['name' => 'Bravo']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.*.id', [
            $this->viewer->id,
            $charlie->id,
            $alpha->id,
            $bravo->id,
        ]);
    }

    /**
     * Sort descending when the column is prefixed with a hyphen.
     */
    #[Test]
    public function it_sorts_descending_when_the_column_is_prefixed_with_a_hyphen(): void
    {
        // Arrange

        User::factory()->for($this->team)->create(['name' => 'Alpha']);
        User::factory()->for($this->team)->create(['name' => 'Charlie']);
        User::factory()->for($this->team)->create(['name' => 'Bravo']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?sort=-name');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.*.name', ['Viewer Manager', 'Charlie', 'Bravo', 'Alpha']);
    }

    /*
     * Include and Fields Tests
     * ------------------------
     */

    /**
     * Omit the Team key for a teamless User when Team is included.
     */
    #[Test]
    /**
     * Eager-load every allow-listed include.
     */
    #[DataProvider('allowedIncludeProvider')]
    public function it_eager_loads_every_allow_listed_include(string $include): void
    {
        /*
         * Sweeping ALLOWED_INCLUDES keeps the allow-list and UserIncludeQuery's
         * eager-load map from drifting apart as relations are added.
         */

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson("/api/users?include={$include}");

        // Assert

        $response->assertOk();
        $this->assertNotNull($response->json("data.0.{$include}.id"));
    }

    /**
     * Omit the Team key for a teamless User when Team is included.
     */
    #[Test]
    public function it_omits_the_team_key_for_a_user_with_no_team_when_team_is_included(): void
    {
        /*
         * `team_id` is nullable, unlike a typical required foreign key, so a
         * teamless User must not blow up TeamResource with a null resource —
         * this is the one place this endpoint's foreign key is optional.
         *
         * `withDefault()` on the BelongsTo relationship returns a default
         * (empty) Team model when the foreign key is null, so the relation
         * serializes as an object with default values rather than `null`.
         */

        // Arrange

        User::factory()->withoutTeam()->create(['name' => 'Teamless Member']);

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create(['name' => 'Zeta Admin']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users?include=team&sort=name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed>|null $teamless */
        $teamless = collect($this->apiDataItems($response))
            ->firstWhere('name', 'Teamless Member');

        $this->assertNotNull($teamless);
        /** @var array<string, mixed>|null $team */
        $team = $teamless['team'] ?? null;
        $this->assertNotNull($team);
        $this->assertNull($team['id'] ?? null);
        $this->assertNull($team['name'] ?? null);
    }

    /**
     * Return only requested User columns in sparse fieldsets.
     */
    #[Test]
    public function it_returns_only_requested_user_columns_in_sparse_fieldsets(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?fields[users]=id,name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $user */
        $user = $response->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($user));
    }

    /**
     * Keep the include working alongside a sparse User fieldset.
     */
    #[Test]
    public function it_keeps_the_include_working_alongside_a_sparse_user_fieldset(): void
    {
        /*
         * `team_id` is not requestable via `fields[users]`, so the include
         * only resolves if the required-column path selects the foreign key.
         */

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            '/api/users?fields[users]=id,name&include=team',
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.team.name', $this->team->name);

        /** @var array<string, mixed> $user */
        $user = $response->json('data.0');

        $this->assertArrayNotHasKey('email', $user);
        $this->assertArrayNotHasKey('team_id', $user);
    }

    /**
     * Constrain nested Team columns when Team is included.
     */
    #[Test]
    public function it_constrains_nested_team_columns_when_team_is_included(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            '/api/users?include=team&fields[teams]=id,name',
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $teamPayload */
        $teamPayload = $response->json('data.0.team');

        $this->assertSame(['id', 'name'], array_keys($teamPayload));
        $this->assertSame($this->team->name, $teamPayload['name']);
    }

    /**
     * Constrain nested Role columns when Role is included.
     */
    #[Test]
    public function it_constrains_nested_role_columns_when_role_is_included(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            '/api/users?include=role&fields[roles]=id,name',
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $rolePayload */
        $rolePayload = $response->json('data.0.role');

        $this->assertSame(['id', 'name'], array_keys($rolePayload));
        $this->assertSame(RoleName::Manager->value, $rolePayload['name']);
    }

    /**
     * Accept sparse fieldsets with spaces after commas.
     */
    #[Test]
    public function it_accepts_sparse_fieldsets_with_spaces_after_commas(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?fields[users]=id,%20name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $user */
        $user = $response->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($user));
    }

    /**
     * Treat a fieldset that parses to no columns as no constraint.
     */
    #[Test]
    public function it_treats_a_fieldset_that_parses_to_no_columns_as_no_constraint(): void
    {
        /*
         * `fields[users]=,` is a non-blank string (so `filled()` accepts it),
         * but CommaSeparatedList::parse() reduces it to an empty list — that
         * must fall back to an unconstrained select, not an empty one.
         */

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?fields[users]=,');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Viewer Manager');
    }

    /**
     * Reject a sort value that is present but blank.
     */
    #[Test]
    public function it_rejects_a_sort_value_that_is_present_but_blank(): void
    {
        /*
         * The global ConvertEmptyStringsToNull middleware turns the blank
         * value in `sort=` into `null` before validation runs, so a present
         * but empty `sort` key fails the `string` rule rather than falling
         * back to the default sort — unlike omitting `sort` entirely.
         */

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?sort=');

        // Assert

        $this->assertApiValidationErrors($response, ['sort']);
    }

    /*
     * Email Visibility Tests
     * ----------------------
     */

    /**
     * Expose email to a viewer with the view-email permission.
     */
    #[Test]
    public function it_exposes_email_to_a_viewer_with_the_view_email_permission(): void
    {
        /*
         * The admin holds `users.list-all`, so the response spans every
         * Team and the admin's own row is not guaranteed to sort first —
         * assert membership rather than position 0.
         */

        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users?fields[users]=id,email');

        // Assert

        $response->assertOk();
        $this->assertContains($admin->email, (array) $response->json('data.*.email'));
    }

    /**
     * Never expose email to a viewer without the view-email permission.
     */
    #[Test]
    public function it_never_exposes_email_to_a_viewer_without_the_view_email_permission(): void
    {
        /*
         * `manager` never receives `email` in ALLOWED_FIELDS, so requesting it
         * explicitly must be rejected by validation rather than silently
         * dropped — proving the allow-list, not just the Resource, is the gate.
         */

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?fields[users]=id,email');

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['fields.users']);
    }

    /**
     * Never expose email via an unconstrained select without the permission.
     */
    #[Test]
    public function it_never_exposes_email_via_an_unconstrained_select_without_the_permission(): void
    {
        /*
         * A request that never constrains `fields[users]` runs an
         * unqualified `SELECT *`, so the Resource itself — not just sparse
         * fieldset omission — must be the thing keeping email out.
         */

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $user */
        $user = $response->json('data.0');

        $this->assertArrayNotHasKey('email', $user);
    }

    /*
     * Validation and Authorisation Tests
     * ----------------------------------
     */

    /**
     * Return supported values in the validation envelope for allow-list failures.
     */
    #[Test]
    /**
     * Reject query params outside the allow lists.
     */
    #[DataProvider('invalidQueryProvider')]
    public function it_rejects_query_params_outside_the_allow_lists(
        string $queryString,
        string $expectedErrorKey,
    ): void {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson("/api/users?{$queryString}");

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /**
     * Return supported values in the validation envelope for allow-list failures.
     */
    #[Test]
    public function it_returns_supported_values_in_the_validation_envelope_for_allow_list_failures(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/users?fields[users]=id,i');

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['fields.users']);

        $allowed = $this->apiMetaAllowed($response);
        $this->assertSame(
            ['created_at', 'id', 'name'],
            $allowed['fields.users'] ?? null,
        );
        $response->assertJsonFragment([
            'Unsupported Field: i (Supported: created_at, id, name)',
        ]);
    }

    /**
     * Deny unauthenticated requests.
     */
    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    /**
     * Authorise the index according to the Role matrix.
     */
    #[DataProvider('roleAuthorisationProvider')]
    public function it_authorises_the_index_according_to_the_role_matrix(string $role, bool $canList): void
    {
        // Arrange

        $factory = match ($role) {
            RoleName::Admin->value => User::factory()->for($this->team)->admin(),
            RoleName::Manager->value => User::factory()->for($this->team)->manager(),
            RoleName::User->value => User::factory()->for($this->team)->user(),
            default => throw new InvalidArgumentException("Unmapped Role: {$role}"),
        };

        /** @var User $roleViewer */
        $roleViewer = $factory->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($roleViewer)->getJson('/api/users');

        // Assert

        if ($canList) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/users');

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Every relation key the User Index advertises via `?include=`.
     *
     * @return array<string, array{0: string}> include key mapped to [include]
     */
    public static function allowedIncludeProvider(): array
    {
        $cases = [];

        foreach (UserQueryConstraints::ALLOWED_INCLUDES as $include) {
            $cases[$include] = [$include];
        }

        return $cases;
    }

    /**
     * Spatie role names mapped to whether that role may list Users.
     *
     * @return array<string, array{0: string, 1: bool}> case name mapped to [role, canList]
     */
    public static function roleAuthorisationProvider(): array
    {
        return [
            'Admin can list' => [RoleName::Admin->value, true],
            'Manager can list' => [RoleName::Manager->value, true],
            'User cannot list' => [RoleName::User->value, false],
        ];
    }

    /**
     * Hostile and out-of-allow-list Query Params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=password', 'sort'],
            'unknown filter key' => ['filter[team_id]=999', 'filter.team_id'],
            'unknown sparse field' => ['fields[users]=id,password', 'fields.users'],
            'unknown sparse role field' => ['fields[roles]=id,guard_name', 'fields.roles'],
            'unknown fields resource' => ['fields[permissions]=id', 'fields.permissions'],
            'unknown include' => ['include=permissions', 'include'],
            'array-shaped sort' => ['sort[]=name', 'sort'],
            'array-shaped include' => ['include[]=team', 'include'],
            'array-shaped sparse field' => ['fields[users][]=id', 'fields.users'],
            'scalar filter container' => ['filter=name', 'filter'],
            'scalar fields container' => ['fields=name', 'fields'],
            'page size above the hard maximum' => [
                'per_page='.(UserQueryConstraints::MAX_PER_PAGE + 1),
                'per_page',
            ],
            'page size below one' => ['per_page=0', 'per_page'],
            'page below one' => ['page=0', 'page'],
        ];
    }
}
