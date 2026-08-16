<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Auth\ForceLogoutUsersAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RecordAuthAuditAction;
use App\DataTransferObjects\Auth\ForceLogoutUsersData;
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\Users\ForceLogoutUsersController;
use App\Http\Requests\Users\ForceLogoutUsersRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the admin force-logout endpoint.
 */
#[CoversClass(ForceLogoutUsersController::class)]
#[CoversClass(ForceLogoutUsersRequest::class)]
#[CoversClass(ForceLogoutUsersAction::class)]
#[CoversClass(ForceLogoutUsersData::class)]
#[CoversClass(LogoutUserAction::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class ForceLogoutUsersControllerTest extends TestCase
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

    /** @var User an admin viewer with `users.force-logout` */
    private User $admin;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and create the shared admin viewer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard success envelope with the logged-out User ids.
     */
    #[Test]
    public function it_returns_the_standard_success_envelope_with_logged_out_user_ids(): void
    {
        // Arrange

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->postJson('/api/users/logout', [
            'ids' => [$target->id],
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('status_code', 200);
        $response->assertJsonPath('message', 'Users Logged Out Successfully');
        $response->assertJsonPath('data.user_ids', [$target->id]);
        $response->assertJsonPath('meta', []);
    }

    /**
     * Revoke every token, session row, and remember-me state for each target User.
     */
    #[Test]
    public function it_revokes_every_token_session_and_remember_me_state_for_each_target(): void
    {
        // Arrange

        /** @var User $firstTarget */
        $firstTarget = User::factory()->user()->create([
            'remember_token' => 'remember-one',
        ]);

        /** @var User $secondTarget */
        $secondTarget = User::factory()->user()->create([
            'remember_token' => 'remember-two',
        ]);

        $firstToken = $firstTarget->createToken('device-one');
        $secondToken = $secondTarget->createToken('device-two');

        DB::table(config()->string('session.table'))->insert([
            'id' => 'session-one',
            'user_id' => $firstTarget->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        DB::table(config()->string('session.table'))->insert([
            'id' => 'session-two',
            'user_id' => $secondTarget->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->postJson('/api/users/logout', [
            'ids' => [$firstTarget->id, $secondTarget->id],
        ]);

        // Assert

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('users', [
            'id' => $firstTarget->id,
            'remember_token' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $secondTarget->id,
            'remember_token' => null,
        ]);
        $this->assertDatabaseMissing(config()->string('session.table'), [
            'user_id' => $firstTarget->id,
        ]);
        $this->assertDatabaseMissing(config()->string('session.table'), [
            'user_id' => $secondTarget->id,
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $firstTarget->id,
            'event' => AuthAuditEvent::ForcedLogout->value,
            'email' => $firstTarget->email,
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $secondTarget->id,
            'event' => AuthAuditEvent::ForcedLogout->value,
            'email' => $secondTarget->email,
        ]);

        Auth::forgetGuards();

        $this->withToken($firstToken->plainTextToken)
            ->getJson('/api/tokens')
            ->assertUnauthorized();

        $this->withToken($secondToken->plainTextToken)
            ->getJson('/api/tokens')
            ->assertUnauthorized();
    }

    /**
     * Keep the admin's own session active when force-logging out other Users.
     */
    #[Test]
    public function it_keeps_the_admin_session_active_when_force_logging_out_others(): void
    {
        // Arrange

        /** @var User $target */
        $target = User::factory()->user()->create();
        $target->createToken('target-device');

        $adminToken = $this->admin->createToken('admin-device');

        // Act

        $this->actingAs($this->admin)->postJson('/api/users/logout', [
            'ids' => [$target->id],
        ]);

        // Assert

        $this->withToken($adminToken->plainTextToken)
            ->getJson('/api/users')
            ->assertOk();
    }

    /**
     * Force-logout soft-deleted Users so stale tokens cannot keep working.
     */
    #[Test]
    public function it_force_logs_out_soft_deleted_users(): void
    {
        // Arrange

        /** @var User $target */
        $target = User::factory()->user()->create([
            'remember_token' => 'remember-deleted',
        ]);
        $token = $target->createToken('deleted-device');
        $target->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->postJson('/api/users/logout', [
            'ids' => [$target->id],
        ]);

        // Assert

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'remember_token' => null,
        ]);

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/tokens')
            ->assertUnauthorized();
    }

    /**
     * Deny managers without `users.force-logout`.
     */
    #[Test]
    public function it_denies_managers_without_force_logout_permission(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson('/api/users/logout', [
            'ids' => [$target->id],
        ]);

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny standard Users without `users.force-logout`.
     */
    #[Test]
    public function it_denies_standard_users_without_force_logout_permission(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/users/logout', [
            'ids' => [$target->id],
        ]);

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny unauthenticated force-logout attempts.
     */
    #[Test]
    public function it_denies_unauthenticated_force_logout_attempts(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/users/logout', [
            'ids' => [1],
        ]);

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');
    }

    /**
     * Require the ids array on force-logout requests.
     */
    #[Test]
    public function it_requires_the_ids_array(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->postJson('/api/users/logout', []);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['ids']);
    }

    /**
     * Reject invalid force-logout payloads.
     *
     * @param array<string, mixed> $payload          the hostile request body
     * @param string               $expectedErrorKey the validation key that must error
     */
    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function it_rejects_invalid_force_logout_payloads(array $payload, string $expectedErrorKey): void
    {
        // Arrange

        /** @var User $target */
        $target = User::factory()->user()->create();

        if (! isset($payload['ids'])) {
            $payload['ids'] = [$target->id];
        }

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->postJson('/api/users/logout', $payload);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /**
     * Reject payloads that exceed the maximum number of User ids.
     */
    #[Test]
    public function it_rejects_more_than_the_maximum_number_of_user_ids(): void
    {
        // Arrange

        $ids = User::factory()
            ->count(ForceLogoutUsersRequest::MAX_USER_IDS + 1)
            ->create()
            ->pluck('id')
            ->all();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->admin)->postJson('/api/users/logout', [
            'ids' => $ids,
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['ids']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Invalid payloads mapped to the validation key that must error.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'empty ids array' => [
                ['ids' => []],
                'ids',
            ],
            'duplicate ids' => [
                ['ids' => [1, 1]],
                'ids.1',
            ],
            'unknown user id' => [
                ['ids' => [99999]],
                'ids.0',
            ],
            'non-integer id' => [
                ['ids' => ['abc']],
                'ids.0',
            ],
        ];
    }
}
