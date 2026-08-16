<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Http\Controllers\Users\ForceLogoutUsersController;
use App\Http\Requests\Users\ForceLogoutUsersRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for force-logout validation on service accounts.
 */
#[CoversClass(ForceLogoutUsersController::class)]
#[CoversClass(ForceLogoutUsersRequest::class)]
final class ForceLogoutServiceAccountTest extends TestCase
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
     * Seed permissions for force-logout authorisation.
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
     * Reject force-logout payloads that target service account ids.
     */
    #[Test]
    public function it_rejects_force_logout_for_service_account_ids(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users/logout', [
            'ids' => [$serviceUser->id],
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['ids.0']);
    }
}
