<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Team;
use App\Models\User;
use App\Queries\Users\UserQueryConstraints;
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
 * Adversarial feature probes — hostile input, injection-shaped payloads, and abuse attempts.
 */
#[CoversClass(ApiResponse::class)]
final class ApiSecurityProbeTest extends TestCase
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

    /** @var User an Admin viewer for probes that need broad access */
    private User $admin;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and create a shared Admin viewer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->admin()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Injection-Shaped Search Tests
     * -----------------------------
     */

    /**
     * Survive SQL-injection-shaped search terms without error.
     */
    #[Test]
    /**
     * Survive SQL-injection-shaped search terms without error.
     */
    #[DataProvider('sqlInjectionSearchProvider')]
    public function it_survives_sql_injection_shaped_search_terms_without_error(string $endpoint): void
    {
        // Arrange

        $payload = urlencode("'; DROP TABLE users;--");

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->getJson("{$endpoint}?filter[search]={$payload}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $this->assertGreaterThan(0, User::query()->count());
    }

    /*
     * Mass Assignment and Privilege Escalation Tests
     * ----------------------------------------------
     */

    /**
     * Ignore privilege escalation fields on User update.
     */
    #[Test]
    public function it_ignores_privilege_escalation_fields_on_user_update(): void
    {
        // Arrange

        $team = Team::factory()->create();
        $manager = User::factory()->for($team)->manager()->create();
        $member = User::factory()->for($team)->user()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->patchJson("/api/users/{$member->id}", [
            'name' => 'Updated Member',
            'email' => 'hacked@evil.com',
            'password' => 'super-secret',
            'team_id' => Team::factory()->create()->id,
            'is_admin' => true,
            'remember_token' => 'stolen',
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['email', 'password']);
        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'name' => 'Member',
            'email' => 'member@example.com',
            'team_id' => $team->id,
        ]);
    }

    /*
     * Boundaries and Method Tampering Tests
     * -------------------------------------
     */

    /**
     * Accept per_page at the hard maximum.
     */
    #[Test]
    public function it_accepts_per_page_at_the_hard_maximum(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->getJson(
            '/api/users?per_page='.UserQueryConstraints::MAX_PER_PAGE,
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('meta.pagination.per_page', UserQueryConstraints::MAX_PER_PAGE);
    }

    /**
     * Reject an overlong search term.
     */
    #[Test]
    public function it_rejects_an_overlong_search_term(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->getJson(
            '/api/users?filter[search]='.str_repeat('a', 256),
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['filter.search']);
    }

    /**
     * Reject PUT on a patch-only User update route.
     */
    #[Test]
    public function it_rejects_put_on_a_patch_only_user_update_route(): void
    {
        // Arrange

        $member = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->putJson(
            "/api/users/{$member->id}",
            ['name' => 'Tampered'],
        );

        // Assert

        $response->assertStatus(405);
        $response->assertJsonPath('message', 'Method Not Allowed');
    }

    /**
     * Deny access to a Role from a foreign guard.
     */
    #[Test]
    public function it_denies_access_to_a_role_from_a_foreign_guard(): void
    {
        // Arrange

        /** @var \Spatie\Permission\Models\Role $foreignRole */
        $foreignRole = \Spatie\Permission\Models\Role::query()->create([
            'name' => 'foreign-guard-role',
            'guard_name' => 'sanctum',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->getJson("/api/roles/{$foreignRole->id}");

        // Assert

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Forbidden');
    }

    /**
     * Reject a massive include list without executing it.
     */
    #[Test]
    public function it_rejects_a_massive_include_list_without_executing_it(): void
    {
        // Arrange

        $includes = implode(',', array_fill(0, 50, 'permissions'));

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->getJson("/api/users?include={$includes}");

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['include']);

        $allowed = $this->apiMetaAllowed($response);
        $this->assertNotEmpty($allowed['include'] ?? []);
    }

    /*
     * Authentication Writable Endpoint Tests
     * ----------------------------------------
     */

    /**
     * Survive hostile login and registration payloads without server error.
     *
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('hostileAuthPayloadProvider')]
    public function it_survives_hostile_auth_payloads_without_server_error(string $endpoint, array $payload): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson($endpoint, $payload);

        // Assert

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->json('status'), ['success', 'error']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Hostile payloads for public authentication endpoints.
     *
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function hostileAuthPayloadProvider(): array
    {
        $injection = "'; DROP TABLE users;--";

        return [
            'login sql injection email' => [
                '/api/auth/login',
                ['email' => $injection, 'password' => 'Xq7#mK2$vL9pTzW4'],
            ],
            'register sql injection name' => [
                '/api/auth/register',
                [
                    'name' => $injection,
                    'email' => 'probe@example.com',
                    'password' => 'Xq7#mK2$vL9pTzW4',
                    'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
                ],
            ],
            'register oversized email' => [
                '/api/auth/register',
                [
                    'name' => 'Alice',
                    'email' => str_repeat('a', 300).'@example.com',
                    'password' => 'Xq7#mK2$vL9pTzW4',
                    'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
                ],
            ],
        ];
    }

    /**
     * Index endpoints that accept `filter[search]`.
     *
     * @return array<string, array{0: string}>
     */
    public static function sqlInjectionSearchProvider(): array
    {
        return [
            'users index' => ['/api/users'],
            'tokens index' => ['/api/tokens'],
            'roles index' => ['/api/roles'],
            'sessions index' => ['/api/sessions'],
        ];
    }
}
