<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the webhook endpoint policy.
 */
#[CoversClass(WebhookEndpointPolicy::class)]
final class WebhookEndpointPolicyTest extends TestCase
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
     * Seed permissions for the Spatie role gate.
     *
     * @return void
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
     * Allow admins full endpoint management.
     */
    #[Test]
    public function it_allows_admins_to_manage_endpoints(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act + Assert

        $this->actingAs($admin)->getJson('/api/webhook-endpoints')->assertOk();
        $this->actingAs($admin)->getJson("/api/webhook-endpoints/{$endpoint->id}")->assertOk();
        $this->actingAs($admin)->patchJson("/api/webhook-endpoints/{$endpoint->id}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/webhook-endpoints/{$endpoint->id}")->assertOk();
    }

    /**
     * Deny managers, users, and service accounts on every endpoint route.
     */
    #[Test]
    public function it_denies_everyone_without_webhooks_permissions(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var Team $team */
        $team = Team::factory()->create();

        // Act + Assert

        foreach ([$manager, $user, $serviceUser] as $viewer) {
            $this->actingAs($viewer)->getJson('/api/webhook-endpoints')->assertForbidden();
            $this->actingAs($viewer)->getJson("/api/webhook-endpoints/{$endpoint->id}")->assertForbidden();
            $this->actingAs($viewer)->patchJson("/api/webhook-endpoints/{$endpoint->id}", ['name' => 'Renamed'])->assertForbidden();
            $this->actingAs($viewer)->deleteJson("/api/webhook-endpoints/{$endpoint->id}")->assertForbidden();
            $this->actingAs($viewer)->getJson("/api/webhook-endpoints/{$endpoint->id}/deliveries")->assertForbidden();
            $this->actingAs($viewer)->postJson("/api/webhook-endpoints/{$endpoint->id}/test")->assertForbidden();
            $this->actingAs($viewer)->postJson("/api/webhook-endpoints/{$endpoint->id}/rotate-secret")->assertForbidden();
        }

        $this->assertDatabaseHas('webhook_endpoints', ['id' => $endpoint->id, 'name' => $endpoint->name]);
    }
}
