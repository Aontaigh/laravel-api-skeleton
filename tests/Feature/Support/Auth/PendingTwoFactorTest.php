<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Auth;

use App\Models\User;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for PendingTwoFactor session and cache behaviour.
 */
#[CoversClass(PendingTwoFactor::class)]
final class PendingTwoFactorTest extends TestCase
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
     * Prefer the session expiry stamp over the opaque-token cache when both exist.
     */
    #[Test]
    public function it_prefers_the_session_expiry_stamp_over_the_opaque_token_cache(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $token = PendingTwoFactor::begin($user->id, false, null);

        session()->put(PendingTwoFactor::EXPIRES_AT_KEY, 1_700_000_000);

        Cache::put(PendingTwoFactor::PENDING_CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'remember' => false,
            'device_name' => null,
            'expires_at' => 1_800_000_000,
        ], 300);

        // Act

        $expiresAt = PendingTwoFactor::expiresAt($token);

        // Assert

        $this->assertSame(1_700_000_000, $expiresAt);
    }

    /**
     * Read the opaque-token cache when the session carries no expiry stamp.
     */
    #[Test]
    public function it_reads_the_opaque_token_cache_when_the_session_has_no_expiry_stamp(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $token = 'opaque-test-token';

        session()->forget(PendingTwoFactor::EXPIRES_AT_KEY);

        Cache::put(PendingTwoFactor::PENDING_CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'remember' => false,
            'device_name' => null,
            'expires_at' => 1_800_000_000,
        ], 300);

        // Act

        $expiresAt = PendingTwoFactor::expiresAt($token);

        // Assert

        $this->assertSame(1_800_000_000, $expiresAt);
    }
}
