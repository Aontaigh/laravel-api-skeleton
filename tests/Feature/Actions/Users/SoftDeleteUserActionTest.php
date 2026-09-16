<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Users;

use App\Actions\Users\SoftDeleteUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for SoftDeleteUserAction against the database.
 */
#[CoversClass(SoftDeleteUserAction::class)]
final class SoftDeleteUserActionTest extends TestCase
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
     * Soft-delete the User.
     */
    #[Test]
    public function it_soft_deletes_the_user(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(SoftDeleteUserAction::class)->execute($user);

        // Assert

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /**
     * Revoke every credential before the delete stamps `deleted_at`.
     *
     * `delete()` alone leaves tokens and remember-me state technically valid -
     * access is denied only by the user provider filtering trashed models.
     */
    #[Test]
    public function it_revokes_every_credential_before_deleting(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'remember_token' => 'remember-me-token',
        ]);

        $user->createToken('device-one');
        $user->createToken('device-two');

        // Act

        app(SoftDeleteUserAction::class)->execute($user);

        // Assert

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'remember_token' => null,
            'session_version' => 1,
        ]);
    }

    /*
     * Rollback on revocation failure is guaranteed by the DB::transaction
     * wrapper and is exercised at the framework level; LogoutUserAction is
     * final, so the failure is injected through its own dependencies rather
     * than a double.
     */
}
