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
        $client->setRelation('user', new User([
            'is_service_account' => false,
            'suspended_at' => null,
        ]));

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
        $client->setRelation('user', new User([
            'is_service_account' => false,
            'suspended_at' => null,
        ]));

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
}
