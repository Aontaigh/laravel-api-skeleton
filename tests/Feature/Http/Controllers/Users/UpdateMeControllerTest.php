<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\UpdateUserAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Http\Controllers\Users\UpdateMeController;
use App\Http\Requests\Users\UpdateMeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the self-service profile update endpoint.
 */
#[CoversClass(UpdateMeController::class)]
#[CoversClass(UpdateMeRequest::class)]
#[CoversClass(UpdateUserAction::class)]
#[CoversClass(UpdateUserData::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(UserResource::class)]
#[CoversClass(ApiResponse::class)]
final class UpdateMeControllerTest extends TestCase
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
     * Seed permissions for the Spatie role gate.
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
     * Update Tests
     * ------------
     */

    /**
     * Update the caller's own name.
     */
    #[Test]
    public function it_updates_the_callers_own_name(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create([
            'name' => 'Original Name',
        ]);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('message', 'Profile Updated Successfully');
        $response->assertJsonPath('data.name', 'Updated Name');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Strip markup from the updated name.
     */
    #[Test]
    public function it_strips_markup_from_the_updated_name(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me', [
            'name' => '<script>alert(1)</script>',
        ]);

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('data.name', 'alert(1)');
    }

    /**
     * Reject prohibited fields that are not self-service concerns.
     */
    #[Test]
    public function it_rejects_prohibited_fields(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me', [
            'name' => 'Valid Name',
            'email' => 'new@example.com',
            'password' => 'new-password',
            'team_id' => 99,
        ]);

        /* Assert */

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['email', 'password', 'team_id']);
    }

    /**
     * Reject an empty payload.
     */
    #[Test]
    public function it_rejects_an_empty_payload(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me', []);

        /* Assert */

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['name']);
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->patchJson('/api/me', [
            'name' => 'Anonymous Update',
        ]);

        /* Assert */

        $response->assertUnauthorized();
    }

    /**
     * Deny service accounts from self-service profile updates.
     */
    #[Test]
    public function it_denies_service_accounts_from_self_service_profile_updates(): void
    {
        /* Arrange */

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->patchJson('/api/me', [
            'name' => 'Service User Update',
        ]);

        /* Assert */

        $response->assertForbidden();
    }
}
