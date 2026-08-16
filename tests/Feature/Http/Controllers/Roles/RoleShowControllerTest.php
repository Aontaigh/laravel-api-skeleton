<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Roles;

use App\Enums\RoleName;
use App\Http\Controllers\Roles\RoleShowController;
use App\Http\Requests\Roles\RoleShowRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\Permissions\PermissionQueryConstraints;
use App\Queries\Roles\RoleIncludeQuery;
use App\Queries\Roles\RoleQueryConstraints;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the Role Show Endpoint.
 */
#[CoversClass(RoleShowController::class)]
#[CoversClass(RoleShowRequest::class)]
#[CoversClass(RoleResource::class)]
#[CoversClass(PermissionResource::class)]
#[CoversClass(RolePolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(RoleIncludeQuery::class)]
#[CoversClass(RoleQueryConstraints::class)]
#[CoversClass(PermissionQueryConstraints::class)]
final class RoleShowControllerTest extends TestCase
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
     * Seed permissions and enable strict model checks.
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
     * Show Tests
     * ----------
     */

    /**
     * Return a Role by id.
     */
    #[Test]
    public function it_returns_a_role_by_id(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var Role $adminRole */
        $adminRole = Role::findByName(RoleName::Admin->value);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/roles/{$adminRole->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Role Retrieved Successfully');
        $response->assertJsonPath('data.id', $adminRole->id);
        $response->assertJsonPath('data.name', RoleName::Admin->value);
    }

    /**
     * Include Permissions when requested.
     */
    #[Test]
    public function it_includes_permissions_when_requested(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var Role $adminRole */
        $adminRole = Role::findByName(RoleName::Admin->value);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/roles/{$adminRole->id}?include=permissions&fields[permissions]=id,name",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'permissions' => [
                    ['id', 'name'],
                ],
            ],
        ]);
    }

    /**
     * Apply sparse fieldsets on the Role.
     */
    #[Test]
    public function it_applies_sparse_fieldsets_on_the_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var Role $managerRole */
        $managerRole = Role::findByName(RoleName::Manager->value);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/roles/{$managerRole->id}?fields[roles]=id,name",
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json('data');

        $this->assertSame(['id', 'name'], array_keys($payload));
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
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var Role $adminRole */
        $adminRole = Role::findByName(RoleName::Admin->value);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/roles/{$adminRole->id}?include=team",
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['include']);
    }

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
            RoleName::Admin->value => User::factory()->admin(),
            RoleName::Manager->value => User::factory()->manager(),
            RoleName::User->value => User::factory()->user(),
            default => throw new InvalidArgumentException("Unmapped Role: {$role}"),
        };

        /** @var User $viewer */
        $viewer = $factory->create();

        /** @var Role $adminRole */
        $adminRole = Role::findByName(RoleName::Admin->value);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->getJson("/api/roles/{$adminRole->id}");

        // Assert

        if ($canView) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }

    /**
     * Return not found for a nonexistent Role.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles/999999');

        // Assert

        $response->assertNotFound();
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Arrange

        /** @var Role $adminRole */
        $adminRole = Role::findByName(RoleName::Admin->value);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson("/api/roles/{$adminRole->id}");

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Spatie role names mapped to whether that role may show a Role.
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
