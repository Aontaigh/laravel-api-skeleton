<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for service-account authentication guardrails.
 */
#[CoversClass(AuthenticateUserAction::class)]
#[CoversClass(LoginController::class)]
final class ServiceAccountAuthTest extends TestCase
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
     * Seed permissions for role assignment.
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
     * Block service accounts from password login with a generic message.
     */
    #[Test]
    public function it_blocks_service_accounts_from_password_login(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create([
            'password' => Hash::make('ServicePass12'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/login', [
            'email' => $serviceUser->email,
            'password' => 'ServicePass12',
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.errors.email', ['Invalid Credentials']);
    }
}
