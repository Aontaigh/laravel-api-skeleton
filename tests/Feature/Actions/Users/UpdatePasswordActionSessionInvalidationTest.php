<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Users;

use App\Actions\Users\UpdatePasswordAction;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Models\User;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for full session invalidation on password change.
 */
#[CoversClass(UpdatePasswordAction::class)]
final class UpdatePasswordActionSessionInvalidationTest extends TestCase
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
     * Seed the roles the User factory assigns.
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
     * Revoke every Personal Access Token alongside the version bump.
     *
     * PATs sit outside `session_version`, so a password change that only bumped
     * the version would leave them usable - closing that gap is the point.
     */
    #[Test]
    public function it_revokes_all_personal_access_tokens_on_password_change(): void
    {
        // Arrange

        $password = 'OldSecret99';

        /** @var User $user */
        $user = User::factory()->user()->create();

        $user->forceFill(['password' => Hash::make($password)])->saveQuietly();

        $user->createToken('cli')->plainTextToken;
        $user->createToken('mobile')->plainTextToken;

        $this->assertSame(2, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        // Act

        app(UpdatePasswordAction::class)->execute(
            $user,
            new UpdatePasswordData(currentPassword: $password, newPassword: 'NewSecret99'),
        );

        // Assert

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    /**
     * Stamp every other web session revoked while keeping the current cookie live.
     */
    #[Test]
    public function it_revokes_other_web_sessions_but_keeps_the_current_session(): void
    {
        // Arrange

        $password = 'OldSecret99';

        /** @var User $user */
        $user = User::factory()->user()->create();

        $user->forceFill(['password' => Hash::make($password)])->saveQuietly();

        $currentSession = WebSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'session-current',
            'device_name' => 'Linux · Firefox',
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
        ]);

        $otherSession = WebSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'session-other',
            'device_name' => 'macOS · Chrome',
            'ip_address' => '198.51.100.2',
            'last_activity_at' => now(),
        ]);

        // Act

        app(UpdatePasswordAction::class)->execute(
            $user,
            new UpdatePasswordData(currentPassword: $password, newPassword: 'NewSecret99'),
            exceptSessionId: 'session-current',
        );

        // Assert

        $this->assertNull($currentSession->refresh()->revoked_at, 'current session must stay active');
        $this->assertNotNull($otherSession->refresh()->revoked_at, 'other session must be revoked');
    }
}
