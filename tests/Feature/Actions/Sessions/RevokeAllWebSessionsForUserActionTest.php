<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Sessions;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeAllWebSessionsForUserAction;
use App\Models\User;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for RevokeAllWebSessionsForUserAction against the database.
 */
#[CoversClass(RevokeAllWebSessionsForUserAction::class)]
#[CoversClass(InvalidateStoredSessionAction::class)]
final class RevokeAllWebSessionsForUserActionTest extends TestCase
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
     * Seed permissions before factory roles are assigned.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Mark every active registry row revoked for the target User.
     */
    #[Test]
    public function it_marks_every_active_registry_row_revoked_for_the_target_user(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $firstActive = WebSession::factory()->for($user)->create();
        $secondActive = WebSession::factory()->for($user)->create();
        $alreadyRevoked = WebSession::factory()->for($user)->revoked()->create();
        $foreignSession = WebSession::factory()->create();

        // Act

        app(RevokeAllWebSessionsForUserAction::class)->execute($user);

        // Assert

        $this->assertNotNull($firstActive->fresh()?->revoked_at);
        $this->assertNotNull($secondActive->fresh()?->revoked_at);
        $this->assertNotNull($alreadyRevoked->fresh()?->revoked_at);
        $this->assertNull($foreignSession->fresh()?->revoked_at);
    }
}
