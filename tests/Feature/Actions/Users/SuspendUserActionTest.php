<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Users;

use App\Actions\Auth\LogoutUserAction;
use App\Actions\Users\SuspendUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for SuspendUserAction against the database.
 */
#[CoversClass(SuspendUserAction::class)]
#[CoversClass(LogoutUserAction::class)]
final class SuspendUserActionTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Mark the User as suspended.
     */
    #[Test]
    public function it_marks_the_user_as_suspended(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(SuspendUserAction::class)->execute($user);

        // Assert

        $this->assertNotNull($user->fresh()?->suspended_at);
    }

    /**
     * Revoke every Personal Access Token when suspending so stale Bearer credentials cannot be reused.
     */
    #[Test]
    public function it_revokes_every_personal_access_token_when_suspending(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'remember_token' => 'remember-me',
        ]);

        $user->createToken('device-one');
        $user->createToken('device-two');

        DB::table(config()->string('session.table'))->insert([
            'id' => 'session-one',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $originalSessionVersion = $user->session_version;

        // Act

        app(SuspendUserAction::class)->execute($user);

        // Assert

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'remember_token' => null,
            'session_version' => $originalSessionVersion + 1,
        ]);
        $this->assertDatabaseMissing(config()->string('session.table'), [
            'user_id' => $user->id,
        ]);
    }
}
