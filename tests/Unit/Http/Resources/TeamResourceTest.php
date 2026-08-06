<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for TeamResource serialisation.
 */
#[CoversClass(TeamResource::class)]
final class TeamResourceTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------​
    | Setup
    |--------------------------------------------------------------------------​
    */

    /**
     * Build a Team with all attributes set directly.
     */
    private function fullTeam(): Team
    {
        $team = new Team;
        $team->id = 1;
        $team->name = 'Engineering';

        return $team;
    }

    /*
    |--------------------------------------------------------------------------​
    | Tests
    |--------------------------------------------------------------------------​
    */

    /**
     * Serialise all fields when no sparse fieldset is requested.
     */
    #[Test]
    public function it_includes_all_fields_when_no_sparse_fieldset_is_requested(): void
    {
        // Arrange

        $team = $this->fullTeam();

        // Act

        $data = (new TeamResource($team))->resolve(Request::create('/api/teams'));

        // Assert

        $this->assertSame(['id' => 1, 'name' => 'Engineering'], $data);
    }

    /**
     * Omit fields that were not selected in a sparse fieldset.
     */
    #[Test]
    public function it_omits_unselected_fields_in_a_sparse_fieldset(): void
    {
        // Arrange

        $team = new Team;
        $team->id = 1;

        // Act

        $data = (new TeamResource($team))->resolve(Request::create('/api/teams'));

        // Assert

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('name', $data);
    }
}
