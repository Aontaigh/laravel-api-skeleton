<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AuthAuditLogs;

use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Enums\ClientIneligibilityReason;
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
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and enable strict model checks.
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
     * Show Tests
     * ----------
     */

    /**
     * Return an auth audit log row by ID.
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

    /**
     * Serialise the refusal reason and honour sparse fieldsets for it.
     *
     * `outcome: refused` alone cannot distinguish which policy declined an exchange, so the reason
     * is what makes the row actionable during an incident. It was persisted but unreachable until
     * it was added to both `AuthAuditLogResource` and `ALLOWED_FIELDS`; this test fails if either
     * drops it again.
     */
    #[Test]
    public function it_serialises_the_client_ineligibility_reason(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $log = AuthAuditLog::factory()->create([
            'outcome' => AuditOutcome::Refused,
            'client_ineligibility_reason' => ClientIneligibilityReason::SuspendedOwner,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/audit-logs/{$log->id}");

        /** @var TestResponse<JsonResponse> $sparse */
        $sparse = $this->actingAs($admin)->getJson(
            "/api/audit-logs/{$log->id}?fields[auth_audit_logs]=id,client_ineligibility_reason",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.outcome', 'refused');
        $this->assertSame(
            ClientIneligibilityReason::SuspendedOwner->value,
            $response->json('data.client_ineligibility_reason'),
        );

        $sparse->assertOk();

        /** @var array<string, mixed> $sparsePayload */
        $sparsePayload = $sparse->json('data');
        $this->assertSame(
            ['id', 'client_ineligibility_reason'],
            array_keys($sparsePayload),
            'Selecting the reason must narrow the payload to it plus the primary key.',
        );
    }

    /**
     * Serialise a null refusal reason without failing.
     *
     * Every outcome other than `refused` records no reason, so the Resource has to tolerate null
     * rather than assuming the column is always populated.
     */
    #[Test]
    public function it_serialises_a_null_refusal_reason_on_a_non_refused_row(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $log = AuthAuditLog::factory()->create([
            'outcome' => AuditOutcome::Failed,
            'client_ineligibility_reason' => null,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/audit-logs/{$log->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.outcome', 'failed');
        $this->assertNull(
            $response->json('data.client_ineligibility_reason'),
            'A non-refused row carries no reason.',
        );
    }

    /**
     * Serialise the outcome on the audit log row and honour sparse fieldsets
     * for it.
     *
     * The full payload carries `outcome` as its enum value, and selecting it
     * through `fields[auth_audit_logs]=` narrows the payload to `id` plus
     * `outcome` only - a field outside the allow-list can never leak in.
     */
    #[Test]
    public function it_serialises_the_outcome_and_honours_sparse_fieldsets_for_it(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $log = AuthAuditLog::factory()->create(['outcome' => AuditOutcome::Failed]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/audit-logs/{$log->id}");

        /** @var TestResponse<JsonResponse> $sparse */
        $sparse = $this->actingAs($admin)->getJson(
            "/api/audit-logs/{$log->id}?fields[auth_audit_logs]=id,outcome",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.outcome', 'failed');

        $sparse->assertOk();

        /** @var array<string, mixed> $sparsePayload */
        $sparsePayload = $sparse->json('data');

        $this->assertSame(['id', 'outcome'], array_keys($sparsePayload));
        $this->assertSame('failed', $sparsePayload['outcome']);
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
     * Refuse a service account that holds the permission itself.
     *
     * The show endpoint runs the same `isAdminViewer()` guard as the index, and it deserves the
     * same pin: without the permission granted first, the Service role matrix produces the 403 on
     * its own and the guard could be deleted without a test failing.
     */
    #[Test]
    public function it_refuses_a_service_account_even_when_the_permission_is_mis_assigned(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->create();
        $serviceUser->givePermissionTo(AuthAuditLogPolicy::LIST_PERMISSION);

        $log = AuthAuditLog::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->getJson("/api/audit-logs/{$log->id}");

        // Assert

        $this->assertTrue(
            $serviceUser->can(AuthAuditLogPolicy::LIST_PERMISSION),
            'The permission must be held, otherwise the refusal proves nothing.',
        );
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
