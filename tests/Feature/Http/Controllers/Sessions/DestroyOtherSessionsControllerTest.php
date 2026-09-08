<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Sessions;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeOtherWebSessionsForUserAction;
use App\Http\Controllers\Sessions\DestroyOtherSessionsController;
use App\Http\Requests\Sessions\DestroyOtherSessionsRequest;
use App\Models\User;
use App\Models\WebSession;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MakesStatefulSpaRequests;
use Tests\TestCase;

/**
 * Feature tests for revoking every session except the current browser.
 */
#[CoversClass(DestroyOtherSessionsController::class)]
#[CoversClass(DestroyOtherSessionsRequest::class)]
#[CoversClass(RevokeOtherWebSessionsForUserAction::class)]
#[CoversClass(InvalidateStoredSessionAction::class)]
#[CoversClass(ApiResponse::class)]
final class DestroyOtherSessionsControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use MakesStatefulSpaRequests;
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions for session revoke authorisation.
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
     * Mutation Tests
     * --------------
     */

    /**
     * Revoke every other session while the current browser stays signed in.
     */
    #[Test]
    public function it_revokes_other_sessions_and_keeps_the_current_browser(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'email' => 'revoke-others@example.com',
            'password' => Hash::make('password'),
        ]);

        $statefulHeaders = $this->statefulRequestHeaders();
        $xsrfToken = $this->beginStatefulSession($statefulHeaders);

        /** @var TestResponse<JsonResponse> $loginResponse */
        $loginResponse = $this->withCredentials()
            ->withHeaders($this->statefulRequestHeaders($xsrfToken))
            ->postJson('/api/auth/login', [
                'email' => 'revoke-others@example.com',
                'password' => 'password',
                'device_name' => 'PHPUnit',
            ]);

        $loginResponse->assertOk();
        $this->storeResponseCookies($loginResponse);

        /** @var string|null $plainTextTokenValue */
        $plainTextTokenValue = $loginResponse->json('data.plain_text_token');

        $plainTextToken = $this->requireNonEmptyString(
            $plainTextTokenValue,
            'Login did not return a bearer token',
        );

        /** @var WebSession $otherSession */
        $otherSession = WebSession::factory()->for($user)->create([
            'device_name' => 'Old Phone',
        ]);

        $deleteXsrfToken = $this->beginStatefulSession($statefulHeaders);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withCredentials()
            ->withToken($plainTextToken)
            ->withHeaders($this->statefulRequestHeaders($deleteXsrfToken))
            ->deleteJson('/api/sessions/others');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Other Sessions Revoked Successfully');

        $this->assertNotNull($otherSession->fresh()?->revoked_at);

        $currentSession = WebSession::query()
            ->where('user_id', $user->id)
            ->where('device_name', 'PHPUnit')
            ->first();

        $this->assertNotNull($currentSession);
        $this->assertNull($currentSession->revoked_at);

        // Act + Assert: the surviving browser still authenticates.

        /** @var TestResponse<JsonResponse> $meResponse */
        $meResponse = $this->withCredentials()
            ->withToken($plainTextToken)
            ->withHeaders($this->statefulRequestHeaders($deleteXsrfToken))
            ->getJson('/api/me');

        $meResponse->assertOk();
    }

    /**
     * Revoke every own session for a bearer-only caller with no cookie session.
     */
    #[Test]
    public function it_revokes_every_own_session_for_a_bearer_only_caller(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        WebSession::factory()->for($user)->count(2)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->deleteJson('/api/sessions/others');

        // Assert

        $response->assertOk();

        $this->assertSame(
            0,
            WebSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->count(),
        );
    }

    /**
     * Leave another User's sessions untouched.
     */
    #[Test]
    public function it_leaves_another_users_sessions_untouched(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->user()->create();

        /** @var WebSession $foreignSession */
        $foreignSession = WebSession::factory()->for($otherUser)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->deleteJson('/api/sessions/others');

        // Assert

        $response->assertOk();
        $this->assertNull($foreignSession->fresh()?->revoked_at);
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->deleteJson('/api/sessions/others');

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny service accounts: they hold no browser sessions to prune.
     */
    #[Test]
    public function it_denies_service_accounts(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->deleteJson('/api/sessions/others');

        // Assert

        $response->assertForbidden();
    }
}
