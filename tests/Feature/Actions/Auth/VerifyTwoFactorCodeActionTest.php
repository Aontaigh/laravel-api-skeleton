<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\IssueTwoFactorChallengeAction;
use App\Actions\Auth\VerifyTwoFactorCodeAction;
use App\Exceptions\Auth\TwoFactorChallengeException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for VerifyTwoFactorCodeAction against the database and cache.
 */
#[CoversClass(VerifyTwoFactorCodeAction::class)]
final class VerifyTwoFactorCodeActionTest extends TestCase
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
     * Reject a code after the stored expiry timestamp has passed.
     */
    #[Test]
    public function it_rejects_a_code_after_the_stored_expiry_timestamp(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();
        $cacheKey = IssueTwoFactorChallengeAction::cacheKey($user);

        Cache::put($cacheKey, [
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subSecond()->timestamp,
        ], now()->addMinute());

        // Act + Assert

        try {
            app(VerifyTwoFactorCodeAction::class)->execute($user, '123456');
            $this->fail('Expected TwoFactorChallengeException was not thrown.');
        } catch (TwoFactorChallengeException $exception) {
            $this->assertSame('Invalid or Expired Code', $exception->getMessage());
        }
    }
}
