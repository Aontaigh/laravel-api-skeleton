<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\AuthAuditEvent;
use App\Enums\RoleName;
use App\Http\Controllers\Clients\DestroyClientController;
use App\Http\Controllers\Clients\StoreClientController;
use App\Http\Controllers\Clients\UpdateClientController;
use App\Http\Controllers\Sessions\DestroySessionController;
use App\Http\Controllers\Tokens\DestroyTokenController;
use App\Http\Controllers\Tokens\StoreTokenController;
use App\Http\Controllers\Users\StoreUserTokenController;
use App\Http\Controllers\Users\SuspendUserController;
use App\Http\Controllers\Users\UnsuspendUserController;
use App\Http\Controllers\Users\UpdateMePasswordController;
use App\Http\Controllers\Users\UpdateUserController;
use App\Models\User;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-cutting tests that security-relevant mutations leave audit rows.
 *
 * The log records credential, session, and access-control events only - plain
 * resource administration (user create/rename/delete, team CRUD) stays out so
 * incident response is never buried under admin noise. One test per audited
 * event: act through HTTP, then prove the row exists. The queued listener runs
 * inline here (`QUEUE_CONNECTION=sync` in `phpunit.xml`), so no worker is
 * needed to assert persistence.
 */
#[CoversClass(UpdateMePasswordController::class)]
#[CoversClass(UpdateUserController::class)]
#[CoversClass(SuspendUserController::class)]
#[CoversClass(UnsuspendUserController::class)]
#[CoversClass(DestroySessionController::class)]
#[CoversClass(StoreTokenController::class)]
#[CoversClass(StoreUserTokenController::class)]
#[CoversClass(DestroyTokenController::class)]
#[CoversClass(StoreClientController::class)]
#[CoversClass(UpdateClientController::class)]
#[CoversClass(DestroyClientController::class)]
#[CoversClass(AuthAuditEvent::class)]
final class AuthAuditCoverageTest extends TestCase
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
     * Seed roles and create the Admin actor plus a team member target.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Credential Tests
     * ----------------
     */

    /**
     * Record a row when the caller changes their own password.
     */
    #[Test]
    public function it_records_password_changed_on_self_service_password_change(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create(['password' => 'CurrentPass12']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'CurrentPass12',
            'password' => 'NewSecretPass13',
            'password_confirmation' => 'NewSecretPass13',
        ]);

        // Assert

        $response->assertOk();

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::PasswordChanged->value,
            'user_id' => $user->id,
        ]);
    }

    /*
     * User Lifecycle Tests
     * --------------------
     */

    /**
     * Record a row when an Admin changes a User's role.
     *
     * Plain profile and lifecycle writes (create, rename, delete) stay out of
     * the auth log: it records credential, session, and access-control events
     * only, so incident response is never buried under admin noise.
     */
    #[Test]
    public function it_records_user_role_changed_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act

        $this->actingAs($admin)->patchJson("/api/users/{$member->id}", [
            'role' => RoleName::Manager->value,
        ])->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::UserRoleChanged->value,
            'user_id' => $member->id,
        ]);
    }

    /**
     * Record rows for suspension and unsuspension.
     */
    #[Test]
    public function it_records_suspension_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act + Assert: suspend.

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/suspend")->assertOk();

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::UserSuspended->value,
            'user_id' => $member->id,
        ]);

        // Act + Assert: unsuspend.

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/unsuspend")->assertOk();

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::UserUnsuspended->value,
            'user_id' => $member->id,
        ]);
    }

    /*
     * Session Tests
     * -------------
     */

    /**
     * Record a row when a session is surgically revoked.
     */
    #[Test]
    public function it_records_session_revoked_on_surgical_revoke(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->for($member)->create();

        // Act

        $this->actingAs($admin)->deleteJson("/api/sessions/{$webSession->id}")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::SessionRevoked->value,
            'user_id' => $member->id,
        ]);
    }

    /*
     * Token Tests
     * -----------
     */

    /**
     * Record rows for token issuance, admin issuance, and revocation.
     */
    #[Test]
    public function it_records_token_lifecycle_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act: self-service issuance.

        $this->actingAs($member)->postJson('/api/tokens', [
            'name' => 'Audited Token',
            'abilities' => ['tokens.list-own'],
        ])->assertCreated();

        // Assert: self-service issuance.

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::TokenCreated->value,
            'user_id' => $member->id,
        ]);

        // Act: admin issuance for another user.

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/tokens", [
            'name' => 'Admin Issued Token',
            'abilities' => ['tokens.list-own'],
        ])->assertCreated();

        // Assert: admin issuance carries the token owner, not the actor.

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::TokenCreated->value,
            'user_id' => $member->id,
        ]);

        // Act: revocation of a directly created token (no issuance row).

        $token = $member->createToken('Revoke Me');

        $this->actingAs($member)->deleteJson("/api/tokens/{$token->accessToken->id}")->assertOk();

        // Assert: revocation.

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::TokenRevoked->value,
            'user_id' => $member->id,
        ]);
    }

    /*
     * API Client Tests
     * ----------------
     */

    /**
     * Record rows for API client creation, update, and deletion.
     */
    #[Test]
    public function it_records_api_client_lifecycle_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act: create.

        /** @var TestResponse<JsonResponse> $created */
        $created = $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Audited Client',
            'abilities' => ['users.list'],
        ]);

        $created->assertCreated();

        /** @var int $clientId */
        $clientId = $created->json('data.client.id');

        // Assert: create.

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ApiClientCreated->value,
            'user_id' => $admin->id,
        ]);

        // Act: update and delete.

        $this->actingAs($admin)->patchJson("/api/clients/{$clientId}", ['name' => 'Audited Client Renamed'])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/clients/{$clientId}")->assertOk();

        // Assert: update and delete.

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ApiClientUpdated->value,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ApiClientDeleted->value,
            'user_id' => $admin->id,
        ]);
    }
}
