<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\ApiClients;

use App\Actions\ApiClients\CreateApiClientAction;
use App\DataTransferObjects\ApiClients\CreateApiClientData;
use App\Enums\RoleName;
use App\Exceptions\InvalidTokenAbilitiesException;
use App\Models\ApiClient;
use App\Models\User;
use App\Services\Permissions\PermissionAbilityCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for CreateApiClientAction against the database.
 */
#[CoversClass(CreateApiClientAction::class)]
#[CoversClass(CreateApiClientData::class)]
#[CoversClass(PermissionAbilityCatalog::class)]
final class CreateApiClientActionTest extends TestCase
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
     * Persist a service account and client with normalised abilities.
     */
    #[Test]
    public function it_persists_a_service_account_and_client_with_normalised_abilities(): void
    {
        // Arrange

        $data = new CreateApiClientData(
            name: 'Billing Sync',
            abilities: ['users.list', 'users.list-all'],
        );

        // Act

        $result = app(CreateApiClientAction::class)->execute($data);

        // Assert

        $this->assertDatabaseHas('api_clients', [
            'name' => 'Billing Sync',
            'is_active' => true,
        ]);

        $serviceUser = User::query()->where('is_service_account', true)->first();
        $this->assertNotNull($serviceUser);
        $this->assertTrue($serviceUser->hasRole(RoleName::Service->value));
        $this->assertSame(['users.list', 'users.list-all'], $result->client->abilities);
        $this->assertNotEmpty($result->plainTextSecret);
    }

    /**
     * Reject unknown abilities before persisting a client row.
     */
    #[Test]
    public function it_rejects_unknown_abilities_before_persisting(): void
    {
        // Arrange

        $data = new CreateApiClientData(
            name: 'Bad Abilities',
            abilities: ['read'],
        );

        // Act + Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        try {
            app(CreateApiClientAction::class)->execute($data);
        } finally {
            $this->assertDatabaseCount('api_clients', 0);
        }
    }

    /**
     * Roll back the service User when the client insert fails.
     *
     * A forced exception inside the client insert must not leave an orphaned
     * service-account User behind — the two writes share one transaction.
     */
    #[Test]
    public function it_rolls_back_the_service_user_when_the_client_insert_fails(): void
    {
        // Arrange

        $data = new CreateApiClientData(
            name: 'Rollback Probe',
            abilities: ['users.list'],
        );

        ApiClient::creating(static function (): void {
            throw new RuntimeException('Forced client insert failure');
        });

        // Act + Assert

        $this->expectException(RuntimeException::class);

        try {
            app(CreateApiClientAction::class)->execute($data);
        } finally {
            $this->assertDatabaseCount('api_clients', 0);
            $this->assertDatabaseMissing('users', ['is_service_account' => true]);
        }
    }
}
