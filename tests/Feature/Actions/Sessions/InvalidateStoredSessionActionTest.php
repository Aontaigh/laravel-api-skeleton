<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Sessions;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Http\Middleware\EnsureSessionVersionMatches;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for stored-session payload destruction and fail-closed recall.
 */
#[CoversClass(InvalidateStoredSessionAction::class)]
final class InvalidateStoredSessionActionTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build a mock session handler with the given destroy behaviour.
     *
     * @param  bool          $destroyResult    the boolean the handler returns
     * @param  string|null   $destroyException the exception message when the store throws
     * @return MockInterface the mocked handler
     */
    private function mockHandler(bool $destroyResult = true, ?string $destroyException = null): MockInterface
    {
        $handler = Mockery::mock();

        if ($destroyException !== null) {
            $handler->shouldReceive('destroy')->andThrow(new RuntimeException($destroyException));
        } else {
            $handler->shouldReceive('destroy')->andReturn($destroyResult);
        }

        return $handler;
    }

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

    /*
     * Destroy Result Tests
     * --------------------
     */

    /**
     * Report success when the session handler destroys the payload.
     */
    #[Test]
    public function it_reports_success_when_the_handler_destroys_the_payload(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        $result = (new InvalidateStoredSessionAction)->execute('session-id-1', $user);

        // Assert

        $this->assertTrue($result);
    }

    /**
     * Report failure without throwing when the handler refuses the destroy.
     */
    #[Test]
    public function it_reports_failure_when_the_handler_refuses_the_destroy(): void
    {
        // Arrange

        $handler = $this->mockHandler(destroyResult: false);

        Session::shouldReceive('getHandler')->andReturn($handler);

        // Act

        $result = (new InvalidateStoredSessionAction)->execute('session-id-2');

        // Assert

        $this->assertFalse($result);
    }

    /**
     * Report failure without throwing when the session store throws.
     */
    #[Test]
    public function it_reports_failure_when_the_session_store_throws(): void
    {
        // Arrange

        $handler = $this->mockHandler(destroyException: 'store unavailable');

        Session::shouldReceive('getHandler')->andReturn($handler);

        // Act

        $result = (new InvalidateStoredSessionAction)->execute('session-id-3');

        // Assert

        $this->assertFalse($result);
    }

    /*
     * Fail-Closed Tests
     * -----------------
     */

    /**
     * Bump the session version so a failed destroy still recalls every cookie.
     */
    #[Test]
    public function it_bumps_the_session_version_on_fail_closed(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        /** @var int $versionBefore */
        $versionBefore = $user->session_version;

        // Act

        (new InvalidateStoredSessionAction)->failClosed($user);

        // Assert

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame($versionBefore + 1, $fresh->session_version);
    }

    /**
     * Restamp the caller's own live session on fail-closed so they stay signed in.
     */
    #[Test]
    public function it_restamps_the_callers_own_session_on_fail_closed(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->startSession();

        session()->put(EnsureSessionVersionMatches::SESSION_KEY, $user->session_version);

        // Act

        (new InvalidateStoredSessionAction)->failClosed($user);

        // Assert

        $this->assertSame(
            $user->session_version,
            session()->get(EnsureSessionVersionMatches::SESSION_KEY),
        );
    }

    /**
     * Never restamp the live session when the actor is not the session owner.
     */
    #[Test]
    public function it_does_not_restamp_a_foreign_actors_session(): void
    {
        // Arrange

        /** @var User $victim */
        $victim = User::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->create();

        $this->actingAs($admin);
        $this->startSession();

        $before = session()->get(EnsureSessionVersionMatches::SESSION_KEY);

        // Act

        (new InvalidateStoredSessionAction)->failClosed($victim);

        // Assert

        $this->assertSame($before, session()->get(EnsureSessionVersionMatches::SESSION_KEY));
    }

    /*
     * Logging Tests
     * -------------
     */

    /**
     * Destroy-failure logs must carry a fingerprint, never the raw session id.
     */
    #[Test]
    public function it_logs_a_fingerprint_instead_of_the_raw_session_id(): void
    {
        // Arrange

        $handler = $this->mockHandler(destroyResult: false);

        Session::shouldReceive('getHandler')->andReturn($handler);
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
            $fingerprint = $context['session_fingerprint'] ?? '';

            return $message === 'Session Payload Destroy Failed'
                && is_string($fingerprint)
                && strlen($fingerprint) === 12
                && ! str_contains($fingerprint, 'raw-session-id-value');
        });

        // Act

        (new InvalidateStoredSessionAction)->execute('raw-session-id-value');

        // Assertions made on the mocked logger above.
    }
}
