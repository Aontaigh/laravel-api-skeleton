<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Sessions;

use App\Models\User;
use App\Models\WebSession;
use App\Queries\Sessions\CurrentWebSessionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CurrentWebSessionQuery::class)]
final class CurrentWebSessionQueryTest extends TestCase
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
     * The query resolves the caller's live registry row for the inbound
     * session ID.
     */
    #[Test]
    public function it_resolves_the_callers_current_session_row(): void
    {
        // Arrange

        $user = User::factory()->create();
        $webSession = WebSession::factory()->create([
            'user_id' => $user->id,
            'session_id' => 'session-one',
        ]);

        $query = new CurrentWebSessionQuery;

        // Act

        $resolved = $query->resolve($user, 'session-one');

        // Assert

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($webSession));
    }

    /**
     * A revoked row no longer resolves, so a stale session ID cannot be used
     * after sign-out.
     */
    #[Test]
    public function it_ignores_revoked_rows(): void
    {
        // Arrange

        $user = User::factory()->create();
        WebSession::factory()->create([
            'user_id' => $user->id,
            'session_id' => 'session-one',
            'revoked_at' => now(),
        ]);

        $query = new CurrentWebSessionQuery;

        // Act

        $resolved = $query->resolve($user, 'session-one');

        // Assert

        $this->assertNull($resolved);
    }

    /**
     * Rows belonging to another User never resolve, so the lookup cannot be
     * steered onto someone else's session by replaying a session ID.
     */
    #[Test]
    public function it_ignores_rows_owned_by_another_user(): void
    {
        // Arrange

        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        WebSession::factory()->create([
            'user_id' => $owner->id,
            'session_id' => 'session-one',
        ]);

        $query = new CurrentWebSessionQuery;

        // Act

        $resolved = $query->resolve($viewer, 'session-one');

        // Assert

        $this->assertNull($resolved);
    }

    /**
     * A null session ID (bearer-token-only caller) resolves nothing without
     * touching the database.
     */
    #[Test]
    public function it_resolves_nothing_without_a_session_id(): void
    {
        // Arrange

        $user = User::factory()->create();
        $query = new CurrentWebSessionQuery;

        // Act

        $resolved = $query->resolve($user, null);

        // Assert

        $this->assertNull($resolved);
    }
}
