<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\RevokePasswordResetTokensAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for {@see RevokePasswordResetTokensAction}.
 */
#[CoversClass(RevokePasswordResetTokensAction::class)]
#[CoversClass(User::class)]
final class RevokePasswordResetTokensActionTest extends TestCase
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
     * Delete the outstanding reset token row for the User.
     */
    #[Test]
    public function it_deletes_the_outstanding_reset_token(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        Password::createToken($user);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // Act

        app(RevokePasswordResetTokensAction::class)->execute($user);

        // Assert

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * Do nothing harmful when there is no outstanding token.
     */
    #[Test]
    public function it_is_idempotent_for_a_user_without_an_outstanding_token(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(RevokePasswordResetTokensAction::class)->execute($user);

        // Assert

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
