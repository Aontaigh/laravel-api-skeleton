<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\AuthenticateClientCredentialsAction;
use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for AuthenticateClientCredentialsAction.
 */
#[CoversClass(AuthenticateClientCredentialsAction::class)]
#[CoversClass(ClientCredentialsData::class)]
final class AuthenticateClientCredentialsActionTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Attach a persisted service User relation for credential-exchange unit tests.
     */
    private function attachServiceUser(ApiClient $client, ?callable $configure = null): User
    {
        $serviceUser = new User([
            'is_service_account' => true,
            'suspended_at' => null,
        ]);
        $serviceUser->id = 10;
        $serviceUser->exists = true;

        if ($configure !== null) {
            $configure($serviceUser);
        }

        $client->setRelation('user', $serviceUser);

        return $serviceUser;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the client when the secret matches.
     */
    #[Test]
    public function it_returns_the_client_when_the_secret_matches(): void
    {
        // Arrange

        $plainSecret = 'KnownClientSecret1';
        $client = new ApiClient([
            'client_id' => 'client-one',
            'client_secret' => Hash::make($plainSecret),
            'is_active' => true,
        ]);
        $client->id = 1;
        $this->attachServiceUser($client);

        $action = new AuthenticateClientCredentialsAction(
            static fn (string $clientId): ?ApiClient => $clientId === 'client-one' ? $client : null,
        );

        // Act

        $resolved = $action->execute(new ClientCredentialsData(
            clientId: 'client-one',
            clientSecret: $plainSecret,
        ));

        // Assert

        $this->assertSame($client, $resolved);
    }

    /**
     * Reject unknown client ids with a generic validation message.
     */
    #[Test]
    public function it_rejects_unknown_client_ids_with_a_generic_message(): void
    {
        // Arrange

        $action = new AuthenticateClientCredentialsAction(
            static fn (string $clientId): ?ApiClient => null,
        );

        // Act + Assert

        try {
            $action->execute(new ClientCredentialsData(
                clientId: 'missing-client',
                clientSecret: 'any-secret-value',
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Invalid Credentials'],
                $exception->errors()['client_id'],
            );
        }
    }

    /**
     * Reject a wrong secret with the same generic message as a missing client.
     */
    #[Test]
    public function it_rejects_a_wrong_secret_with_a_generic_message(): void
    {
        // Arrange

        $client = new ApiClient([
            'client_id' => 'client-two',
            'client_secret' => Hash::make('CorrectSecret1'),
            'is_active' => true,
        ]);
        $this->attachServiceUser($client, static function (User $serviceUser): void {
            $serviceUser->id = 11;
        });

        $action = new AuthenticateClientCredentialsAction(
            static fn (string $clientId): ?ApiClient => $clientId === 'client-two' ? $client : null,
        );

        // Act + Assert

        try {
            $action->execute(new ClientCredentialsData(
                clientId: 'client-two',
                clientSecret: 'WrongSecret1',
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Invalid Credentials'],
                $exception->errors()['client_id'],
            );
        }
    }

    /**
     * Reject a withDefault() placeholder User (no primary key) with a generic message.
     */
    #[Test]
    public function it_rejects_a_missing_service_user_with_a_generic_message(): void
    {
        // Arrange

        $plainSecret = 'KnownClientSecret1';
        $client = new ApiClient([
            'client_id' => 'client-three',
            'client_secret' => Hash::make($plainSecret),
            'is_active' => true,
        ]);
        $client->setRelation('user', new User);

        $action = new AuthenticateClientCredentialsAction(
            static fn (string $clientId): ?ApiClient => $clientId === 'client-three' ? $client : null,
        );

        // Act + Assert

        try {
            $action->execute(new ClientCredentialsData(
                clientId: 'client-three',
                clientSecret: $plainSecret,
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Invalid Credentials'],
                $exception->errors()['client_id'],
            );
        }
    }

    /**
     * Reject soft-deleted service users with the same generic message as a wrong secret.
     */
    #[Test]
    public function it_rejects_soft_deleted_service_users_with_a_generic_message(): void
    {
        // Arrange

        $plainSecret = 'KnownClientSecret1';
        $client = new ApiClient([
            'client_id' => 'client-four',
            'client_secret' => Hash::make($plainSecret),
            'is_active' => true,
        ]);
        $this->attachServiceUser($client, static function (User $serviceUser): void {
            $serviceUser->deleted_at = now();
        });

        $action = new AuthenticateClientCredentialsAction(
            static fn (string $clientId): ?ApiClient => $clientId === 'client-four' ? $client : null,
        );

        // Act + Assert

        try {
            $action->execute(new ClientCredentialsData(
                clientId: 'client-four',
                clientSecret: $plainSecret,
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Invalid Credentials'],
                $exception->errors()['client_id'],
            );
        }
    }

    /**
     * Reject suspended service users with the same generic message as a wrong secret.
     */
    #[Test]
    public function it_rejects_suspended_service_users_with_a_generic_message(): void
    {
        // Arrange

        $plainSecret = 'KnownClientSecret1';
        $client = new ApiClient([
            'client_id' => 'client-five',
            'client_secret' => Hash::make($plainSecret),
            'is_active' => true,
        ]);
        $this->attachServiceUser($client, static function (User $serviceUser): void {
            $serviceUser->suspended_at = now();
        });

        $action = new AuthenticateClientCredentialsAction(
            static fn (string $clientId): ?ApiClient => $clientId === 'client-five' ? $client : null,
        );

        // Act + Assert

        try {
            $action->execute(new ClientCredentialsData(
                clientId: 'client-five',
                clientSecret: $plainSecret,
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Invalid Credentials'],
                $exception->errors()['client_id'],
            );
        }
    }
}
