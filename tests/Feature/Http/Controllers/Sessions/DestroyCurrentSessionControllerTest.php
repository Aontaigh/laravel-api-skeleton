<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Sessions;

use App\Actions\Sessions\RevokeWebSessionAction;
use App\Http\Controllers\Sessions\DestroyCurrentSessionController;
use App\Http\Requests\Sessions\DestroyCurrentSessionRequest;
use App\Models\User;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MakesStatefulSpaRequests;
use Tests\TestCase;

/**
 * Feature tests for revoking the caller's current browser session.
 */
#[CoversClass(DestroyCurrentSessionController::class)]
#[CoversClass(DestroyCurrentSessionRequest::class)]
#[CoversClass(RevokeWebSessionAction::class)]
final class DestroyCurrentSessionControllerTest extends TestCase
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
     * Revoke the registry row, tear down the live web session, and leave bearer
     * tokens untouched.
     */
    #[Test]
    public function it_revokes_the_matching_registry_row_and_tears_down_the_live_web_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'email' => 'revoke-current@example.com',
            'password' => Hash::make('password'),
        ]);

        $statefulHeaders = $this->statefulRequestHeaders();
        $xsrfToken = $this->beginStatefulSession($statefulHeaders);

        /** @var TestResponse<JsonResponse> $loginResponse */
        $loginResponse = $this->withCredentials()
            ->withHeaders($this->statefulRequestHeaders($xsrfToken))
            ->postJson('/api/auth/login', [
                'email' => 'revoke-current@example.com',
                'password' => 'password',
                'device_name' => 'PHPUnit',
            ]);

        $loginResponse->assertOk();
        $this->storeResponseCookies($loginResponse);

        $webSession = WebSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($webSession);

        /** @var string|null $plainTextTokenValue */
        $plainTextTokenValue = $loginResponse->json('data.plain_text_token');

        $plainTextToken = $this->requireNonEmptyString(
            $plainTextTokenValue,
            'Login did not return a bearer token',
        );

        $deleteXsrfToken = $this->beginStatefulSession($statefulHeaders);

        // Act

        /*
         * Bearer auth satisfies Sanctum; the session cookie must also be present
         * so RevokeWebSessionAction can match the registry row and invalidate the
         * live browser session.
         */
        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withCredentials()
            ->withToken($plainTextToken)
            ->withHeaders($this->statefulRequestHeaders($deleteXsrfToken))
            ->deleteJson('/api/sessions/current');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Session Revoked Successfully');
        $this->assertNotNull($webSession->fresh()?->revoked_at);

        Auth::forgetGuards();

        /** @var TestResponse<JsonResponse> $cookieOnlyResponse */
        $cookieOnlyResponse = $this->withCredentials()
            ->withoutHeader('Authorization')
            ->withHeaders($statefulHeaders)
            ->getJson('/api/me');

        $cookieOnlyResponse->assertUnauthorized();

        /** @var TestResponse<JsonResponse> $bearerResponse */
        $bearerResponse = $this->withToken($plainTextToken)
            ->getJson('/api/sessions');

        $bearerResponse->assertOk();
    }

    /*
     * Not Found Tests
     * ---------------
     */

    /**
     * Return 404 when no active registry row matches the inbound session id.
     */
    #[Test]
    public function it_returns_not_found_when_no_registry_row_matches_the_current_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        $token = $user->createToken('PHPUnit');

        $this->startSession();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($token->plainTextToken)
            ->deleteJson('/api/sessions/current');

        // Assert

        $response->assertNotFound();
        $response->assertJsonPath('message', 'No Active Browser Session Found');
    }
}
