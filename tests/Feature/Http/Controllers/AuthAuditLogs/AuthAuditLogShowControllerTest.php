<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AuthAuditLogs;

use App\Enums\AuthAuditEvent;
use App\Http\Controllers\AuthAuditLogs\AuthAuditLogShowController;
use App\Http\Requests\AuthAuditLogs\AuthAuditLogShowRequest;
use App\Http\Resources\AuthAuditLogResource;
use App\Models\AuthAuditLog;
use App\Models\User;
use App\Policies\AuthAuditLogPolicy;
use App\Queries\AuthAuditLogs\AuthAuditLogIncludeQuery;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the auth audit log show endpoint.
 */
#[CoversClass(AuthAuditLogShowController::class)]
#[CoversClass(AuthAuditLogShowRequest::class)]
#[CoversClass(AuthAuditLogResource::class)]
#[CoversClass(AuthAuditLogPolicy::class)]
#[CoversClass(AuthAuditLogIncludeQuery::class)]
#[CoversClass(AuthAuditLogQueryConstraints::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(ApiResponse::class)]
final class AuthAuditLogShowControllerTest extends TestCase
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
     * Return an auth audit log row by id.
     */
    #[Test]
    public function it_returns_an_auth_audit_log_by_id(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $log = AuthAuditLog::factory()->create([
            'event' => AuthAuditEvent::LoginFailed,
            'email' => 'failed@example.com',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/audit-logs/{$log->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Auth Audit Log Retrieved Successfully');
        $response->assertJsonPath('data.id', $log->id);
        $response->assertJsonPath('data.event', AuthAuditEvent::LoginFailed->value);
        $response->assertJsonPath('data.email', 'failed@example.com');
    }

    /**
     * Include the related user when requested.
     */
    #[Test]
    public function it_includes_the_related_user_when_requested(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $subject */
        $subject = User::factory()->user()->create(['name' => 'Audit Subject']);
        $log = AuthAuditLog::factory()->for($subject)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/audit-logs/{$log->id}?include=user&fields[users]=id,name",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $subject->id);
        $response->assertJsonPath('data.user.name', 'Audit Subject');
        $response->assertJsonMissingPath('data.user.email');
    }

    /**
     * Apply sparse fieldsets on the audit log row.
     */
    #[Test]
    public function it_applies_sparse_fieldsets_on_the_audit_log(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $log = AuthAuditLog::factory()->create(['email' => 'sparse@example.com']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/audit-logs/{$log->id}?fields[auth_audit_logs]=id,event",
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json('data');

        $this->assertSame(['id', 'event'], array_keys($payload));
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
        $log = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/audit-logs/{$log->id}?include=team",
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['include']);
    }

    /**
     * Deny managers without the Admin role.
     */
    #[Test]
    public function it_denies_managers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        $log = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson("/api/audit-logs/{$log->id}");

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
        $log = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/audit-logs/{$log->id}");

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
        $log = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->getJson("/api/audit-logs/{$log->id}");

        // Assert

        $response->assertForbidden();
    }

    /**
     * Return not found for a nonexistent audit log row.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_audit_log(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/audit-logs/999999');

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

        $log = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson("/api/audit-logs/{$log->id}");

        // Assert

        $response->assertUnauthorized();
    }
}
