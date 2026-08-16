<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RestoreUserFromRememberAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for {@see RestoreUserFromRememberAction}.
 */
#[CoversClass(RestoreUserFromRememberAction::class)]
final class RestoreUserFromRememberActionTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the active web guard User when a session exists.
     */
    #[Test]
    public function it_returns_the_active_web_guard_user_when_a_session_exists(): void
    {
        // Arrange

        /** @var User $user */
        $user = new User([
            'id' => 1,
            'team_id' => null,
            'is_service_account' => false,
        ]);
        Auth::guard('web')->login($user);

        // Act

        $restored = app(RestoreUserFromRememberAction::class)->execute();

        // Assert

        $this->assertTrue($restored?->is($user));
    }

    /**
     * Return null when no session or remember cookie exists.
     */
    #[Test]
    public function it_returns_null_when_no_session_or_remember_cookie_exists(): void
    {
        // Act

        $restored = app(RestoreUserFromRememberAction::class)->execute();

        // Assert

        $this->assertNull($restored);
    }
}
