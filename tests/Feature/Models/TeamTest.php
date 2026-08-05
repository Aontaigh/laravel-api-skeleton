<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Team model's relationships.
 */
#[CoversClass(Team::class)]
final class TeamTest extends TestCase
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

    #[Test]
    public function it_has_many_users(): void
    {
        // Arrange

        /** @var Team $team */
        $team = Team::factory()->create();

        User::factory()->for($team)->count(2)->create();
        User::factory()->create();

        // Act

        $users = $team->users;

        // Assert

        $this->assertCount(2, $users);
        $this->assertTrue($users->every(fn (User $user): bool => $user->team_id === $team->id));
    }
}
