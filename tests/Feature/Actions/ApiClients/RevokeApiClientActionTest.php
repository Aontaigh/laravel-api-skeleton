<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\ApiClients;

use App\Actions\ApiClients\RevokeApiClientAction;
use App\Models\ApiClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for RevokeApiClientAction against the database.
 */
#[CoversClass(RevokeApiClientAction::class)]
final class RevokeApiClientActionTest extends TestCase
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
     * Seed roles so service-account factories can assign the Service role.
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
     * Deactivate the client and delete every token on its service User.
     */
    #[Test]
    public function it_deactivates_the_client_and_deletes_every_service_token(): void
    {
        // Arrange

        $client = ApiClient::factory()->create();
        $client->user->createToken('service-token', $client->abilities);

        // Act

        app(RevokeApiClientAction::class)->execute($client);

        // Assert

        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
