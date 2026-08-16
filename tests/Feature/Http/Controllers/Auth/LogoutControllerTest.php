<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RecordAuthAuditAction;
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Requests\Auth\LogoutRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for logout.
 */
#[CoversClass(LogoutController::class)]
#[CoversClass(LogoutRequest::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(LogoutUserAction::class)]
#[CoversClass(ApiResponse::class)]
final class LogoutControllerTest extends TestCase
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
     * Seed permissions so follow-up token requests distinguish auth from authorisation.
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
     * Response Structure Tests
     * --------------------------
     */

    /**
     * Return the standard success envelope with a null data payload.
     */
    #[Test]
    public function it_returns_the_standard_success_envelope(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        $token = $user->createToken('device-one');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($token->plainTextToken)->postJson('/api/logout');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('status_code', 200);
        $response->assertJsonPath('message', 'Logged Out Successfully');
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta', []);
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Log out and revoke every bearer token for the User.
     */
    #[Test]
    public function it_logs_out_and_revokes_every_bearer_token(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'remember_token' => 'remember-me-token',
        ]);

        $firstToken = $user->createToken('device-one');
        $secondToken = $user->createToken('device-two');

        DB::table(config()->string('session.table'))->insert([
            'id' => 'session-one',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($firstToken->plainTextToken)
            ->postJson('/api/logout');

        // Assert

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::Logout->value,
            'email' => $user->email,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'remember_token' => null,
        ]);
        $this->assertDatabaseMissing(config()->string('session.table'), [
            'user_id' => $user->id,
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
     * Reject unauthenticated logout attempts.
     */
    #[Test]
    public function it_rejects_unauthenticated_logout_attempts(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/logout');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');
    }

    /**
     * Reject logout when the bearer token is invalid.
     */
    #[Test]
    public function it_rejects_logout_when_the_bearer_token_is_invalid(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken('not-a-valid-token')->postJson('/api/logout');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Unauthenticated');
    }
}
