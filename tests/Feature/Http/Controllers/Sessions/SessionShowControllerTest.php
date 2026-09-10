<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Sessions;

use App\Http\Controllers\Sessions\SessionShowController;
use App\Http\Requests\Sessions\SessionShowRequest;
use App\Http\Resources\WebSessionResource;
use App\Models\User;
use App\Models\WebSession;
use App\Policies\WebSessionPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\Sessions\SessionIncludeQuery;
use App\Queries\Sessions\SessionQueryConstraints;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Web Session show endpoint.
 */
#[CoversClass(SessionShowController::class)]
#[CoversClass(SessionShowRequest::class)]
#[CoversClass(WebSessionResource::class)]
#[CoversClass(WebSessionPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(SessionIncludeQuery::class)]
#[CoversClass(SessionQueryConstraints::class)]
#[CoversClass(ApiResponse::class)]
final class SessionShowControllerTest extends TestCase
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
     * Seed permissions for the session gates.
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

    /*
     * Show Tests
     * ----------
     */

    /**
     * Return the caller's own session by id.
     */
    #[Test]
    public function it_returns_the_callers_own_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->for($user)->create([
            'device_name' => 'Work Laptop',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Session Retrieved Successfully');
        $response->assertJsonPath('data.id', $webSession->id);
        $response->assertJsonPath('data.device_name', 'Work Laptop');
    }

    /**
     * Let an Admin view another User's session.
     */
    #[Test]
    public function it_allows_an_admin_to_view_another_users_session(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->user()->create();

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->for($otherUser)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.id', $webSession->id);
    }

    /**
     * Answer 404 for a foreign session without `sessions.list-all`, so the
     * viewer never learns whether the row exists.
     */
    #[Test]
    public function it_returns_not_found_for_a_foreign_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var WebSession $foreignSession */
        $foreignSession = WebSession::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/sessions/{$foreignSession->id}");

        // Assert

        $response->assertNotFound();
    }

    /**
     * Hide revoked sessions: the registry answers for active rows only.
     */
    #[Test]
    public function it_returns_not_found_for_a_revoked_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->for($user)->create([
            'revoked_at' => now(),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertNotFound();
    }

    /**
     * Return not found for a nonexistent session.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/sessions/999999');

        // Assert

        $response->assertNotFound();
    }

    /**
     * Support sparse fieldsets and the `user` include.
     */
    #[Test]
    public function it_supports_fields_and_include(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->for($user)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson(
            "/api/sessions/{$webSession->id}?include=user&fields[sessions]=id,device_name&fields[users]=id,name",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.id', $webSession->id);
        $response->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * Reject an unknown include relation.
     */
    #[Test]
    public function it_rejects_an_unknown_include(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->for($user)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/sessions/{$webSession->id}?include=roles");

        // Assert

        $response->assertUnprocessable();
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
        // Arrange

        /** @var WebSession $webSession */
        $webSession = WebSession::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertUnauthorized();
    }
}
