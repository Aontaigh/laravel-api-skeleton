<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Enums\RoleName;
use App\Http\Controllers\Users\UserShowController;
use App\Http\Requests\Users\UserShowRequest;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\Users\UserIncludeQuery;
use App\Queries\Users\UserQueryConstraints;
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
 * Feature tests for the User Show Endpoint.
 */
#[CoversClass(UserShowController::class)]
#[CoversClass(UserShowRequest::class)]
#[CoversClass(UserResource::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(UserIncludeQuery::class)]
#[CoversClass(UserQueryConstraints::class)]
final class UserShowControllerTest extends TestCase
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

    /** @var User a User on the viewer's Team */
    private User $teamMember;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Create the Team, viewer, and team member shared by every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->team = Team::factory()->create();

        $this->viewer = User::factory()->for($this->team)->manager()->create([
            'name' => 'Viewer Manager',
            'email' => 'viewer@example.com',
        ]);

        $this->teamMember = User::factory()->for($this->team)->user()->create([
            'name' => 'Team Member',
            'email' => 'member@example.com',
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
     * Show and Scoping Tests
     * ----------------------
     */

    /**
     * Return a User on the viewer's Team.
     */
    #[Test]
    public function it_returns_a_user_on_the_viewers_team(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$this->teamMember->id}",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'User Retrieved Successfully');
        $response->assertJsonPath('data.id', $this->teamMember->id);
        $response->assertJsonPath('data.name', 'Team Member');
    }

    /**
     * Deny viewing a User on another Team.
     */
    #[Test]
    public function it_denies_viewing_a_user_on_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->for($otherTeam)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$otherUser->id}",
        );

        // Assert

        $response->assertForbidden();
    }

    /**
     * Allow an admin to view a User on another Team.
     */
    #[Test]
    public function it_allows_an_admin_to_view_a_user_on_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->for($otherTeam)->create(['name' => 'Remote User']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/users/{$otherUser->id}",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Remote User');
    }

    /*
     * Include and Fields Tests
     * ------------------------
     */

    /**
     * Include Team and Role when requested.
     */
    #[Test]
    public function it_includes_team_and_role_when_requested(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$this->teamMember->id}?include=team,role",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.team.name', $this->team->name);
        $response->assertJsonPath('data.role.name', RoleName::User->value);
    }

    /**
     * Apply sparse fieldsets on the User and nested Team.
     */
    #[Test]
    public function it_applies_sparse_fieldsets_on_the_user_and_nested_team(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$this->teamMember->id}?fields[users]=id,name&include=team&fields[teams]=name",
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json('data');

        $this->assertSame(['id', 'name', 'team'], array_keys($payload));

        /** @var mixed $teamPayload */
        $teamPayload = $payload['team'];
        $this->assertIsArray($teamPayload);
        $this->assertSame(['id', 'name'], array_keys($teamPayload));
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
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/users/{$this->teamMember->id}?fields[users]=id,email",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.email', 'member@example.com');
    }

    /**
     * Never expose email to a viewer without the view-email permission.
     */
    #[Test]
    public function it_never_exposes_email_to_a_viewer_without_the_view_email_permission(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$this->teamMember->id}",
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json('data');

        $this->assertArrayNotHasKey('email', $payload);
    }

    /**
     * Reject email in sparse fieldsets without the view-email permission.
     */
    #[Test]
    public function it_rejects_email_in_sparse_fieldsets_without_the_view_email_permission(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$this->teamMember->id}?fields[users]=id,email",
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['fields.users']);
    }

    /*
     * Validation and Authorisation Tests
     * ----------------------------------
     */

    /**
     * Reject an unknown include.
     */
    #[Test]
    public function it_rejects_an_unknown_include(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson(
            "/api/users/{$this->teamMember->id}?include=permissions",
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['include']);

        $allowed = $this->apiMetaAllowed($response);
        $this->assertSame(
            ['role', 'team'],
            $allowed['include'] ?? null,
        );
        $response->assertJsonFragment([
            'Unsupported Include: permissions (Supported: role, team)',
        ]);
    }

    /**
     * Authorise show according to the Role matrix.
     */
    /**
     * Authorise show according to the Role matrix.
     */
    #[Test]
    /**
     * Authorise show according to the Role matrix.
     */
    #[DataProvider('roleAuthorisationProvider')]
    public function it_authorises_show_according_to_the_role_matrix(string $role, bool $canView): void
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
        $response = $this->actingAs($roleViewer)->getJson(
            "/api/users/{$this->teamMember->id}",
        );

        // Assert

        if ($canView) {
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
        $response = $this->getJson("/api/users/{$this->teamMember->id}");

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Spatie role names mapped to whether that role may show a User.
     *
     * @return array<string, array{0: string, 1: bool}> case name mapped to [role, canView]
     */
    public static function roleAuthorisationProvider(): array
    {
        return [
            'Admin can show' => [RoleName::Admin->value, true],
            'Manager can show' => [RoleName::Manager->value, true],
            'User cannot show' => [RoleName::User->value, false],
        ];
    }
}
