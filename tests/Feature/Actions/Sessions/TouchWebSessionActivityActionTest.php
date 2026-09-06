<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Sessions;

use App\Actions\Sessions\TouchWebSessionActivityAction;
use App\Models\WebSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the throttled last-activity touch.
 */
#[CoversClass(TouchWebSessionActivityAction::class)]
final class TouchWebSessionActivityActionTest extends TestCase
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
     * Refresh last-activity when the stamp is older than the throttle window.
     */
    #[Test]
    public function it_touches_a_stale_session_row(): void
    {
        // Arrange

        $webSession = WebSession::factory()->create([
            'last_activity_at' => now()->subMinutes(10),
        ]);

        // Act

        (new TouchWebSessionActivityAction)->execute($webSession->session_id);

        // Assert

        $fresh = $webSession->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertNotNull($webSession->last_activity_at);
        $this->assertTrue($fresh->last_activity_at->greaterThan($webSession->last_activity_at));
    }

    /**
     * Leave a recently touched row alone.
     */
    #[Test]
    public function it_leaves_a_recently_touched_row_alone(): void
    {
        // Arrange

        $webSession = WebSession::factory()->create([
            'last_activity_at' => now()->subMinute(),
        ]);

        // Act

        (new TouchWebSessionActivityAction)->execute($webSession->session_id);

        // Assert

        $fresh = $webSession->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertNotNull($webSession->last_activity_at);
        $this->assertTrue($fresh->last_activity_at->equalTo($webSession->last_activity_at));
    }

    /**
     * Never resurrect a revoked row's activity stamp.
     */
    #[Test]
    public function it_ignores_a_revoked_row(): void
    {
        // Arrange

        $webSession = WebSession::factory()->revoked()->create([
            'last_activity_at' => now()->subMinutes(10),
        ]);

        // Act

        (new TouchWebSessionActivityAction)->execute($webSession->session_id);

        // Assert

        $fresh = $webSession->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertNotNull($webSession->last_activity_at);
        $this->assertTrue($fresh->last_activity_at->equalTo($webSession->last_activity_at));
    }

    /**
     * Treat a missing row as a silent no-op.
     */
    #[Test]
    public function it_ignores_an_unknown_session_id(): void
    {
        // Act

        (new TouchWebSessionActivityAction)->execute('no-such-session-id');

        // Assert

        $this->assertSame(0, WebSession::query()->where('session_id', 'no-such-session-id')->count());
    }
}
