<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Users;

use App\Actions\Users\SoftDeleteUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for SoftDeleteUserAction against the database.
 */
#[CoversClass(SoftDeleteUserAction::class)]
final class SoftDeleteUserActionTest extends TestCase
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
     * Soft-delete the User.
     */
    #[Test]
    public function it_soft_deletes_the_user(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        app(SoftDeleteUserAction::class)->execute($user);

        // Assert

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
