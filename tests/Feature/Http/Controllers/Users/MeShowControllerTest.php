<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Http\Controllers\Users\MeShowController;
use App\Http\Requests\Users\MeShowRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\Users\UserIncludeQuery;
use App\Queries\Users\UserQueryConstraints;
use App\Support\ApiResponse;
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
 * Feature tests for the authenticated profile endpoint.
 */
#[CoversClass(MeShowController::class)]
#[CoversClass(MeShowRequest::class)]
#[CoversClass(UserResource::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(RoleResource::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(UserIncludeQuery::class)]
#[CoversClass(UserQueryConstraints::class)]
#[CoversClass(ApiResponse::class)]
final class MeShowControllerTest extends TestCase
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
     * Seed permissions and enable strict Eloquent modes.
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

    /**
     * Return the caller's profile without `users.list`.
     */
    #[Test]
    public function it_returns_the_authenticated_users_profile_without_users_list(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/me?include=team,role');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Profile Retrieved Successfully');
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Test User');
        $response->assertJsonPath('data.email', 'test@example.com');
        $response->assertJsonPath('data.team.id', $user->team_id);
        $response->assertJsonPath('data.role.name', 'User');
    }

    /**
     * Include email on the caller's profile even without `users.view-email`.
     */
    #[Test]
    public function it_includes_email_without_users_view_email_permission(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create([
            'email' => 'manager@example.com',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson('/api/me');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.email', 'manager@example.com');
    }

    /**
     * Deny unauthenticated profile requests.
     */
    #[Test]
    public function it_denies_unauthenticated_profile_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/me');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');
    }

    /**
     * Deny service accounts from the profile endpoint.
     */
    #[Test]
    public function it_denies_service_accounts_from_the_profile_endpoint(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->getJson('/api/me');

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

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/me?{$queryString}");

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
            'unsupported include' => ['include=permissions', 'include'],
            'unknown sparse field' => ['fields[users]=id,secret', 'fields.users'],
            'unknown fields resource' => ['fields[teams]=id,secret', 'fields.teams'],
        ];
    }
}
