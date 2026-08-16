<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Users;

use App\Actions\Users\CreateUserAction;
use App\DataTransferObjects\Users\CreateUserData;
use App\Enums\MfaMethod;
use App\Enums\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CreateUserAction.
 */
#[CoversClass(CreateUserAction::class)]
final class CreateUserActionTest extends TestCase
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
     * Seed roles before assigning them on create.
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
     * Persist a User with a normalised email and email MFA enrolment.
     */
    #[Test]
    public function it_persists_a_user_with_normalised_email_and_email_mfa(): void
    {
        // Arrange

        $data = new CreateUserData(
            name: 'Alice',
            email: 'ALICE@EXAMPLE.COM',
            password: 'Xq7#mK2$vL9pTzW4',
            role: RoleName::Manager,
            teamId: null,
        );

        // Act

        $user = app(CreateUserAction::class)->execute($data);

        // Assert

        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame(MfaMethod::Email, $user->mfa_method);
        $this->assertTrue($user->hasRole(RoleName::Manager->value));
        $this->assertNull($user->email_verified_at);
    }
}
