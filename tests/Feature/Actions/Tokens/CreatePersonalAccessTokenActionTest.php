<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Tokens;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Exceptions\InvalidTokenAbilitiesException;
use App\Models\User;
use App\Services\Permissions\PermissionAbilityCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for CreatePersonalAccessTokenAction against the database.
 */
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(PermissionAbilityCatalog::class)]
final class CreatePersonalAccessTokenActionTest extends TestCase
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
     * Seed the permission catalog the Action validates against.
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
     * Issue a Token with normalized abilities.
     */
    #[Test]
    public function it_issues_a_token_with_normalized_abilities(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'CLI Token',
            abilities: ['tokens.list-own'],
        );

        // Act

        $issued = app(CreatePersonalAccessTokenAction::class)->execute($data);

        // Assert

        $this->assertSame('CLI Token', $issued->accessToken->name);
        $this->assertSame(['tokens.list-own'], $issued->accessToken->abilities);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'CLI Token',
            'tokenable_id' => $user->id,
        ]);
    }

    /**
     * Reject unknown abilities before persisting.
     */
    #[Test]
    public function it_rejects_unknown_abilities_before_persisting(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'Bad Token',
            abilities: ['read'],
        );

        // Act & Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        try {
            app(CreatePersonalAccessTokenAction::class)->execute($data);
        } finally {
            $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Bad Token']);
        }
    }
}
