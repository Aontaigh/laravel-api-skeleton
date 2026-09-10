<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\AuthAuditEvent;
use App\Enums\RoleName;
use App\Http\Controllers\Clients\DestroyClientController;
use App\Http\Controllers\Clients\RotateClientSecretController;
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
use App\Http\Controllers\Webhooks\DestroyWebhookEndpointController;
use App\Http\Controllers\Webhooks\RotateWebhookEndpointSecretController;
use App\Http\Controllers\Webhooks\StoreWebhookEndpointController;
use App\Http\Controllers\Webhooks\UpdateWebhookEndpointController;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
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
#[CoversClass(RotateClientSecretController::class)]
#[CoversClass(StoreWebhookEndpointController::class)]
#[CoversClass(UpdateWebhookEndpointController::class)]
#[CoversClass(DestroyWebhookEndpointController::class)]
#[CoversClass(RotateWebhookEndpointSecretController::class)]
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
     * Seed roles and permissions, and stub receivers: several tests here
     * trigger webhook emissions, and with `QUEUE_CONNECTION=sync` those
     * deliveries would otherwise POST to the factory URL in-request.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Http::fake(['*' => Http::response('ok', 200)]);
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
     * Record a row when an Admin suspends a User.
     */
    #[Test]
    public function it_records_user_suspended_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/suspend")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::UserSuspended->value,
            'user_id' => $member->id,
        ]);
    }

    /**
     * Record a row when an Admin lifts a suspension.
     */
    #[Test]
    public function it_records_user_unsuspended_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/suspend")->assertOk();
        $this->actingAs($admin)->postJson("/api/users/{$member->id}/unsuspend")->assertOk();

        // Assert

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
     * Record a row when the caller issues their own token.
     */
    #[Test]
    public function it_records_token_created_events(): void
    {
        // Arrange

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act

        $this->actingAs($member)->postJson('/api/tokens', [
            'name' => 'Audited Token',
            'abilities' => ['tokens.list-own'],
        ])->assertCreated();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::TokenCreated->value,
            'user_id' => $member->id,
        ]);
    }

    /**
     * Record the token owner - not the issuing Admin - on admin issuance.
     */
    #[Test]
    public function it_records_admin_issued_token_events_against_the_owner(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/tokens", [
            'name' => 'Admin Issued Token',
            'abilities' => ['tokens.list-own'],
        ])->assertCreated();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::TokenCreated->value,
            'user_id' => $member->id,
        ]);
    }

    /**
     * Record a row when a token is revoked.
     */
    #[Test]
    public function it_records_token_revoked_events(): void
    {
        // Arrange

        /** @var User $member */
        $member = User::factory()->user()->create();

        $token = $member->createToken('Revoke Me');

        // Act

        $this->actingAs($member)->deleteJson("/api/tokens/{$token->accessToken->id}")->assertOk();

        // Assert

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
     * Record a row when an Admin creates an API client.
     */
    #[Test]
    public function it_records_api_client_created_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Audited Client',
            'abilities' => ['users.list'],
        ])->assertCreated();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ApiClientCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Record a row when an Admin updates an API client.
     */
    #[Test]
    public function it_records_api_client_updated_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $created */
        $created = $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Audited Client',
            'abilities' => ['users.list'],
        ]);

        /** @var int $clientId */
        $clientId = $created->json('data.client.id');

        $this->actingAs($admin)->patchJson("/api/clients/{$clientId}", ['name' => 'Audited Client Renamed'])->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ApiClientUpdated->value,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Record a row when an Admin deletes an API client.
     */
    #[Test]
    public function it_records_api_client_deleted_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var TestResponse<JsonResponse> $created */
        $created = $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Audited Client',
            'abilities' => ['users.list'],
        ]);

        /** @var int $clientId */
        $clientId = $created->json('data.client.id');

        // Act

        $this->actingAs($admin)->deleteJson("/api/clients/{$clientId}")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ApiClientDeleted->value,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Record a row when an Admin rotates an API client secret.
     */
    #[Test]
    public function it_records_client_secret_rotated_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var TestResponse<JsonResponse> $created */
        $created = $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Audited Client',
            'abilities' => ['users.list'],
        ]);

        $created->assertCreated();

        /** @var int $clientId */
        $clientId = $created->json('data.client.id');

        // Act

        $this->actingAs($admin)->postJson("/api/clients/{$clientId}/rotate-secret")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientSecretRotated->value,
            'user_id' => $admin->id,
        ]);
    }

    /*
     * Webhook Tests
     * -------------
     */

    /**
     * Record a row when an Admin creates a webhook endpoint.
     */
    #[Test]
    public function it_records_webhook_endpoint_created_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        $this->actingAs($admin)->postJson('/api/webhook-endpoints', [
            'name' => 'Audited Hook',
            'url' => 'https://8.8.8.8/hooks',
            'events' => ['user.created'],
        ])->assertCreated();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::WebhookEndpointCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Record a row when an Admin updates a webhook endpoint.
     */
    #[Test]
    public function it_records_webhook_endpoint_updated_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        $this->actingAs($admin)->patchJson("/api/webhook-endpoints/{$endpoint->id}", ['name' => 'Audited Hook V2'])->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::WebhookEndpointUpdated->value,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Record a row when an Admin rotates a webhook signing secret.
     */
    #[Test]
    public function it_records_webhook_secret_rotated_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/webhook-endpoints/{$endpoint->id}/rotate-secret")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::WebhookSecretRotated->value,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Record a row when an Admin deletes a webhook endpoint.
     */
    #[Test]
    public function it_records_webhook_endpoint_deleted_events(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        $this->actingAs($admin)->deleteJson("/api/webhook-endpoints/{$endpoint->id}")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::WebhookEndpointDeleted->value,
            'user_id' => $admin->id,
        ]);
    }
}
