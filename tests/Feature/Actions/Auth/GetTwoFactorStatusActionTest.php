<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\GetTwoFactorStatusAction;
use App\Enums\MfaMethod;
use App\Models\User;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for GetTwoFactorStatusAction against the database and cache.
 */
#[CoversClass(GetTwoFactorStatusAction::class)]
final class GetTwoFactorStatusActionTest extends TestCase
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
     * Tear down frozen time after each test.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the serialised expiry for an active pending challenge.
     */
    #[Test]
    public function it_returns_the_serialised_expiry_for_an_active_pending_challenge(): void
    {
        // Arrange

        Carbon::setTestNow('2026-08-17 12:00:00');

        /** @var User $user */
        $user = User::factory()->create([
            'mfa_method' => MfaMethod::Email,
        ]);

        $token = PendingTwoFactor::begin($user->id, false, null);

        // Act

        $outcome = app(GetTwoFactorStatusAction::class)->execute($token);

        // Assert

        $this->assertTrue($outcome->isSuccess());
        $this->assertSame('2026-08-17T12:05:00+00:00', $outcome->expiresAt);
    }

    /**
     * Reject a suspended User with a distinct forbidden outcome.
     */
    #[Test]
    public function it_rejects_a_suspended_user_with_a_distinct_forbidden_outcome(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create([
            'mfa_method' => MfaMethod::Email,
            'suspended_at' => now(),
        ]);

        $token = PendingTwoFactor::begin($user->id, false, null);

        // Act

        $outcome = app(GetTwoFactorStatusAction::class)->execute($token);

        // Assert

        $this->assertFalse($outcome->isSuccess());
        $this->assertSame('Account Suspended', $outcome->errorMessage);
        $this->assertSame(403, $outcome->statusCode);
    }

    /**
     * Reject a request with no pending challenge.
     */
    #[Test]
    public function it_rejects_a_request_with_no_pending_challenge(): void
    {
        // Act

        $outcome = app(GetTwoFactorStatusAction::class)->execute(null);

        // Assert

        $this->assertFalse($outcome->isSuccess());
        $this->assertSame('Your Sign-In Session Has Expired', $outcome->errorMessage);
        $this->assertSame(422, $outcome->statusCode);
    }
}
