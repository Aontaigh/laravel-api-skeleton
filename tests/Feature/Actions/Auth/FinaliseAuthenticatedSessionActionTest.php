<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\FinaliseAuthenticatedSessionAction;
use App\DataTransferObjects\Auth\FinaliseAuthenticatedSessionData;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for FinaliseAuthenticatedSessionAction against the database.
 */
#[CoversClass(FinaliseAuthenticatedSessionAction::class)]
final class FinaliseAuthenticatedSessionActionTest extends TestCase
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
     * Seed permissions for token issuance.
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
     * Stamp the current session version when a web session is active.
     */
    #[Test]
    public function it_stamps_the_session_version_when_a_web_session_is_active(): void
    {
        // Arrange

        $this->startSession();

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('SecretPass12'),
        ]);

        // Act

        app(FinaliseAuthenticatedSessionAction::class)->execute(new FinaliseAuthenticatedSessionData(
            user: $user,
            deviceName: 'PHPUnit',
            remember: false,
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            regenerateSession: true,
        ));

        // Assert

        $this->assertSame($user->session_version, session()->get('session_version'));
        $this->assertDatabaseHas('web_sessions', [
            'user_id' => $user->id,
            'session_id' => session()->getId(),
            'revoked_at' => null,
        ]);
    }
}
