<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Users;

use App\Actions\Users\UpdateUserAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for UpdateUserAction against the database.
 */
#[CoversClass(UpdateUserAction::class)]
final class UpdateUserActionTest extends TestCase
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
    public function it_updates_the_requested_attributes(): void
    {
        // Arrange

        /** @var Team $team */
        $team = Team::factory()->create();

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $user */
        $user = User::factory()->for($team)->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        // Act

        $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            name: 'Updated Name',
            teamId: $otherTeam->id,
        ));

        // Assert

        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);
        $this->assertSame($otherTeam->id, $updated->team_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com',
            'team_id' => $otherTeam->id,
        ]);
    }
}
