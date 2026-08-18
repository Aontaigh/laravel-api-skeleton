<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\GetTwoFactorStatusAction;
use App\Enums\MfaMethod;
use App\Http\Controllers\Auth\TwoFactorStatusController;
use App\Http\Requests\Auth\TwoFactorStatusRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Auth\PendingTwoFactor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the two-factor pending-challenge status endpoint.
 */
#[CoversClass(TwoFactorStatusController::class)]
#[CoversClass(TwoFactorStatusRequest::class)]
#[CoversClass(GetTwoFactorStatusAction::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(PendingTwoFactor::class)]
final class TwoFactorStatusControllerTest extends TestCase
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
     * Seed roles and permissions.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Tear down frozen time after each test.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the pending challenge status for a session-bound client.
     */
    #[Test]
    public function it_returns_the_pending_challenge_status_for_a_session_bound_client(): void
    {
        // Arrange

        Carbon::setTestNow('2026-08-17 12:00:00');

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ])->assertOk();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/auth/two-factor/status');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Two-Factor Status Retrieved Successfully');
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonPath('data.expires_at', '2026-08-17T12:05:00+00:00');
    }

    /**
     * Return the pending challenge status for a stateless client.
     */
    #[Test]
    public function it_returns_the_pending_challenge_status_for_a_stateless_client(): void
    {
        // Arrange

        Carbon::setTestNow('2026-08-17 12:00:00');

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'stateless-status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        /** @var TestResponse<JsonResponse> $login */
        $login = $this->postJson('/api/auth/login', [
            'email' => 'stateless-status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $twoFactorToken = $login->json('data.two_factor_token');
        $this->assertIsString($twoFactorToken);

        $this->flushSession();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/auth/two-factor/status?two_factor_token='.$twoFactorToken);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonPath('data.expires_at', '2026-08-17T12:05:00+00:00');
    }

    /**
     * Reject a request with no pending challenge.
     */
    #[Test]
    public function it_rejects_a_request_with_no_pending_challenge(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/auth/two-factor/status');

        // Assert

        $this->assertApiErrorEnvelope($response, 422, 'Your Sign-In Session Has Expired');
    }

    /**
     * Reject a suspended User with a distinct 403.
     */
    #[Test]
    public function it_rejects_a_suspended_user_with_a_distinct_forbidden_response(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'suspended-status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
            'suspended_at' => now(),
        ]);

        $twoFactorToken = PendingTwoFactor::begin($user->id, false, null);

        $this->flushSession();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/auth/two-factor/status?two_factor_token='.$twoFactorToken);

        // Assert

        $this->assertApiErrorEnvelope($response, 403, 'Account Suspended');
    }

    /**
     * Reject a stale opaque token after a fresh login supersedes it.
     */
    #[Test]
    public function it_rejects_a_stale_opaque_token(): void
    {
        // Arrange

        User::factory()->create([
            'email' => 'stale-status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'mfa_method' => MfaMethod::Email,
        ]);

        /** @var TestResponse<JsonResponse> $firstLogin */
        $firstLogin = $this->postJson('/api/auth/login', [
            'email' => 'stale-status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        $staleToken = $firstLogin->json('data.two_factor_token');
        $this->assertIsString($staleToken);

        $this->flushSession();

        $this->postJson('/api/auth/login', [
            'email' => 'stale-status@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ])->assertOk();

        $this->flushSession();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/auth/two-factor/status?two_factor_token='.$staleToken);

        // Assert

        $this->assertApiErrorEnvelope($response, 422, 'Your Sign-In Session Has Expired');
    }
}
