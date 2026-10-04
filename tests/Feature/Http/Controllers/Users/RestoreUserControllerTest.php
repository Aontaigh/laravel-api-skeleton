<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\RestoreUserAction;
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\Users\RestoreUserController;
use App\Http\Requests\Users\RestoreUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for {@see RestoreUserController} (`POST /api/users/{user}/restore`).
 */
#[CoversClass(RestoreUserController::class)]
#[CoversClass(RestoreUserRequest::class)]
#[CoversClass(RestoreUserAction::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(User::class)]
final class RestoreUserControllerTest extends TestCase
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
     * Seed roles and permissions before each test.
     *
     * @return void
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

    /**
     * Restore another, soft-deleted User as an Admin.
     */
    #[Test]
    public function it_restores_a_soft_deleted_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $trashed */
        $trashed = User::factory()->user()->create();
        $trashed->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$trashed->id}/restore");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'User Restored Successfully');
        $this->assertNotSoftDeleted('users', ['id' => $trashed->id]);
    }

    /**
     * Record a `User Restored` audit event carrying the acting Admin.
     */
    #[Test]
    public function it_records_a_user_restored_audit_event_with_the_actor(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $trashed */
        $trashed = User::factory()->user()->create();
        $trashed->delete();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$trashed->id}/restore")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::UserRestored->value,
            'user_id' => $trashed->id,
            'actor_user_id' => $admin->id,
        ]);
    }

    /**
     * Reject standard Users through the Policy.
     */
    #[Test]
    public function it_rejects_standard_users(): void
    {
        // Arrange

        /** @var User $caller */
        $caller = User::factory()->user()->create();

        /** @var User $trashed */
        $trashed = User::factory()->user()->create();
        $trashed->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($caller)->postJson("/api/users/{$trashed->id}/restore");

        // Assert

        $response->assertForbidden();
        $this->assertSoftDeleted('users', ['id' => $trashed->id]);
    }

    /**
     * Refuse restoring a live User: the `withTrashed()` route must not turn
     * into a silent no-op success for a record that is not deleted.
     */
    #[Test]
    public function it_refuses_restoring_a_live_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $live */
        $live = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$live->id}/restore");

        // Assert

        $response->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $live->id]);
    }

    /**
     * Answer 404 for a User that does not exist.
     */
    #[Test]
    public function it_answers_not_found_for_an_unknown_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users/999999/restore');

        // Assert

        $response->assertNotFound();
    }

    /**
     * Keep a soft-deleted User out of the directory until they are restored,
     * and list them under the `deleted` status filter so the restore flow has
     * a data source.
     */
    #[Test]
    public function it_hides_a_soft_deleted_user_until_restored(): void
    {
        // Arrange

        $team = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->for($team)->admin()->create();

        /** @var User $trashed */
        $trashed = User::factory()->for($team)->user()->create();
        $trashed->delete();

        // Act

        $hidden = $this->actingAs($admin)->getJson('/api/users');
        $listed = $this->actingAs($admin)->getJson('/api/users?filter[status]=deleted');
        $this->actingAs($admin)->postJson("/api/users/{$trashed->id}/restore")->assertOk();
        $shown = $this->actingAs($admin)->getJson('/api/users');

        // Assert

        /** @var list<array{id: int}> $hiddenRows */
        $hiddenRows = $hidden->json('data');
        $hiddenIds = array_map(static fn (array $row): int => $row['id'], $hiddenRows);
        $this->assertNotContains($trashed->id, $hiddenIds, 'A soft-deleted User must stay out of the directory.');

        /** @var list<array{id: int}> $listedRows */
        $listedRows = $listed->json('data');
        $listedIds = array_map(static fn (array $row): int => $row['id'], $listedRows);
        $this->assertContains($trashed->id, $listedIds, 'A soft-deleted User must appear under the deleted status filter.');

        /** @var list<array{id: int}> $shownRows */
        $shownRows = $shown->json('data');
        $shownIds = array_map(static fn (array $row): int => $row['id'], $shownRows);
        $this->assertContains($trashed->id, $shownIds, 'A restored User must appear in the directory.');
    }

    /**
     * A restored account must sign in again: deletion revoked its credentials
     * and bumped the `session_version`, and restore does neither.
     */
    #[Test]
    public function a_restored_user_must_sign_in_again(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $trashed */
        $trashed = User::factory()->user()->create();
        $token = $trashed->createToken('probe')->plainTextToken;

        /*
         * Delete through the endpoint, not the model: the model's `delete()`
         * only stamps `deleted_at`, while the endpoint's Action also revokes
         * every credential - the behaviour under test.
         */
        $this->actingAs($admin)->deleteJson("/api/users/{$trashed->id}")->assertOk();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$trashed->id}/restore")->assertOk();

        /*
         * `actingAs` pins the guard for the whole test, so the admin would
         * answer the request regardless of the bearer token. Forget the guards
         * so the request authenticates from the token alone.
         */
        Auth::forgetGuards();

        $me = $this->withToken($token)->getJson('/api/me');

        // Assert

        $me->assertUnauthorized();
    }

    /**
     * Refuse restoring a service account, whose lifecycle belongs to the API
     * Client that owns it.
     */
    #[Test]
    public function it_refuses_restoring_a_trashed_service_account(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $serviceAccount */
        $serviceAccount = User::factory()->serviceAccount()->create();
        $serviceAccount->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$serviceAccount->id}/restore");

        // Assert

        /*
         * The scoped `{user}` binding excludes machine identities even among
         * trashed rows: a backing account's lifecycle belongs to its API
         * Client, so its ID answers exactly like an unknown ID.
         */
        $response->assertNotFound();
        $this->assertSoftDeleted('users', ['id' => $serviceAccount->id]);
    }

    /**
     * A Manager holds lifecycle permissions but not `users.restore`: restore
     * pairs with delete and rides the Admin grant.
     */
    #[Test]
    public function it_refuses_a_manager(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var User $trashed */
        $trashed = User::factory()->user()->create();
        $trashed->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson("/api/users/{$trashed->id}/restore");

        // Assert

        $response->assertForbidden();
        $this->assertSoftDeleted('users', ['id' => $trashed->id]);
    }
}
