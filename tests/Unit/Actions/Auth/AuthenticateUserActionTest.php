<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for password credential verification.
 */
#[CoversClass(AuthenticateUserAction::class)]
final class AuthenticateUserActionTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * Fixed bcrypt hash for timing-normalisation assertions.
     */
    private const TIMING_NORMALISATION_HASH = '$2y$04$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Pin the timing-normalisation hash so Hash::check() expectations are stable.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['api.auth_timing_normalisation_hash' => self::TIMING_NORMALISATION_HASH]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the User when the password matches.
     */
    #[Test]
    public function it_returns_the_user_when_credentials_are_valid(): void
    {
        // Arrange

        /** @var User $user */
        $user = new User([
            'email' => 'alice@example.com',
            'password' => 'hashed-secret',
            'team_id' => null,
            'is_service_account' => false,
        ]);

        $action = new AuthenticateUserAction(
            fn (string $email): ?User => $email === 'alice@example.com' ? $user : null,
        );

        Hash::shouldReceive('check')
            ->once()
            ->with('Xq7#mK2$vL9pTzW4', Mockery::type('string'))
            ->andReturn(true);

        // Act

        $authenticated = $action->execute(new LoginCredentialsData(
            email: 'alice@example.com',
            password: 'Xq7#mK2$vL9pTzW4',
        ));

        // Assert

        $this->assertTrue($authenticated->is($user));
    }

    /**
     * Reject unknown emails with a generic validation message.
     */
    #[Test]
    public function it_rejects_unknown_emails_with_a_generic_message(): void
    {
        // Arrange

        $action = new AuthenticateUserAction(
            static fn (string $email): ?User => null,
        );

        Hash::shouldReceive('check')
            ->once()
            ->with('Xq7#mK2$vL9pTzW4', self::TIMING_NORMALISATION_HASH)
            ->andReturn(false);

        // Act + Assert

        try {
            $action->execute(new LoginCredentialsData(
                email: 'missing@example.com',
                password: 'Xq7#mK2$vL9pTzW4',
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(['Invalid Credentials'], $exception->errors()['email']);
        }
    }

    /**
     * Reject wrong passwords with the same generic validation message.
     */
    #[Test]
    public function it_rejects_wrong_passwords_with_a_generic_message(): void
    {
        // Arrange

        /** @var User $user */
        $user = new User([
            'email' => 'alice@example.com',
            'password' => 'hashed-secret',
            'team_id' => null,
            'is_service_account' => false,
        ]);

        $action = new AuthenticateUserAction(
            fn (string $email): ?User => $email === 'alice@example.com' ? $user : null,
        );

        Hash::shouldReceive('check')
            ->once()
            ->with('WrongPass1', Mockery::type('string'))
            ->andReturn(false);

        // Act + Assert

        try {
            $action->execute(new LoginCredentialsData(
                email: 'alice@example.com',
                password: 'WrongPass1',
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(['Invalid Credentials'], $exception->errors()['email']);
        }
    }

    /**
     * Run a dummy password check when the email is unknown to normalise timing.
     */
    #[Test]
    public function it_runs_a_dummy_password_check_for_unknown_emails(): void
    {
        // Arrange

        $action = new AuthenticateUserAction(
            static fn (string $email): ?User => null,
        );

        Hash::shouldReceive('check')
            ->once()
            ->with('Xq7#mK2$vL9pTzW4', self::TIMING_NORMALISATION_HASH)
            ->andReturn(false);

        // Act + Assert

        try {
            $action->execute(new LoginCredentialsData(
                email: 'missing@example.com',
                password: 'Xq7#mK2$vL9pTzW4',
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected — dummy hash check ran before the exception was thrown.
        }
    }

    /**
     * Reject suspended accounts with the same generic validation message.
     */
    #[Test]
    public function it_rejects_suspended_accounts_with_a_generic_message(): void
    {
        // Arrange

        /** @var User $user */
        $user = new User([
            'email' => 'alice@example.com',
            'password' => 'hashed-secret',
            'team_id' => null,
            'is_service_account' => false,
        ]);
        $user->suspended_at = now();

        $action = new AuthenticateUserAction(
            fn (string $email): ?User => $email === 'alice@example.com' ? $user : null,
        );

        Hash::shouldReceive('check')
            ->once()
            ->with('Xq7#mK2$vL9pTzW4', Mockery::type('string'))
            ->andReturn(true);

        // Act + Assert

        try {
            $action->execute(new LoginCredentialsData(
                email: 'alice@example.com',
                password: 'Xq7#mK2$vL9pTzW4',
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(['Invalid Credentials'], $exception->errors()['email']);
        }
    }
}
