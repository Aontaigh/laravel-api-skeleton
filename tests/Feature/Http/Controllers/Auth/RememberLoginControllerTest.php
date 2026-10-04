<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\RecordAuthAuditAction;
use App\Actions\Auth\RestoreUserFromRememberAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\Auth\RememberLoginController;
use App\Http\Requests\Auth\RememberLoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for remember-me session restoration.
 */
#[CoversClass(RememberLoginController::class)]
#[CoversClass(RememberLoginRequest::class)]
#[CoversClass(RestoreUserFromRememberAction::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(AuthenticatedUserResource::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(ApiResponse::class)]
final class RememberLoginControllerTest extends TestCase
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
     * Seed permissions for token issuance.
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
     * Restore a session from the web guard and issue a fresh bearer token.
     */
    #[Test]
    public function it_restores_a_session_and_issues_a_fresh_bearer_token(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $this->startSession();
        Auth::guard('web')->login($user, true);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Session Restored Successfully');
        $this->assertNotEmpty($response->json('data.plain_text_token'));
        $this->assertSame($user->session_version, session()->get('session_version'));
        $this->assertDatabaseHas('web_sessions', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::RememberMeLogin->value,
            'remember_me' => true,
        ]);
    }

    /**
     * Strip markup from the optional device name before issuing a token.
     */
    #[Test]
    public function it_strips_markup_from_the_device_name(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        Auth::guard('web')->login($user, true);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember', [
            'device_name' => '<script>alert(1)</script>Mobile App',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.token.name', 'alert(1)Mobile App');
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'alert(1)Mobile App',
        ]);
    }

    /**
     * Reject remember-me restoration without a valid session or cookie.
     *
     * The generic answer is unchanged, but the attempt is audited as
     * `failed`: a remember-me restoration that finds no cookie is an
     * authentication attempt like any other, and leaving it unrecorded means
     * the audit trail shows only successful remember-me events. No principal is
     * known on this path, so `user_id` stays null.
     */
    #[Test]
    public function it_rejects_remember_me_restoration_without_a_valid_session(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::RememberMeLogin->value,
            'outcome' => AuditOutcome::Failed->value,
            'user_id' => null,
            'remember_me' => true,
        ]);
    }

    /**
     * Reject suspended users with the same generic 401 as a bad remember cookie.
     */
    #[Test]
    public function it_rejects_suspended_users_with_a_generic_unauthenticated_response(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->suspended()->create();
        Auth::guard('web')->login($user, true);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');

        /*
         * The generic answer still withholds *whether* the cookie resolved,
         * but the audit trail is an internal record and names the principal.
         * A suspension blocked at the remember-me door is a policy refusal, so
         * the outcome is `refused` rather than `failed` - matching how the
         * password sign-in path classifies the same account state.
         */
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::RememberMeLogin->value,
            'outcome' => AuditOutcome::Refused->value,
            'remember_me' => true,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /**
     * Refuse a service account at the remember-me door with an audited outcome.
     *
     * A machine identity has no interactive surface, so restoring a remember-me
     * session for one is the same policy refusal as a password sign-in, and it
     * is recorded as such rather than left untracked.
     */
    #[Test]
    public function it_refuses_a_service_account_at_the_remember_me_door(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->serviceAccount()->create();
        Auth::guard('web')->login($user, true);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::RememberMeLogin->value,
            'outcome' => AuditOutcome::Refused->value,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /**
     * Restore cleanly with a session bound.
     *
     * The controller regenerates the session at the privilege boundary when one
     * is bound; this proves the stateful restore succeeds end-to-end. The
     * rotation itself is exercised directly against the session store (the
     * testing harness does not expose the post-request store).
     */
    #[Test]
    public function it_restores_with_a_bound_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $this->startSession();
        Auth::guard('web')->login($user, true);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember');

        // Assert

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.plain_text_token'));
    }
}
