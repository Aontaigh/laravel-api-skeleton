<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookEvent;
use App\Events\WebhookEventDispatched;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves domain writes emit webhook events end to end over HTTP.
 *
 * The fan-out mechanics live in `DispatchWebhookDeliveriesTest`; these tests
 * pin the wiring from each resource controller to a delivery row.
 */
#[CoversClass(WebhookEventDispatched::class)]
final class WebhookEmissionTest extends TestCase
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
     * Seed roles and permissions, and stub receivers so inline sync delivery
     * never leaves the machine: with `QUEUE_CONNECTION=sync` the emission
     * path runs `DeliverWebhookJob` in-request, and these tests assert fan-out
     * rows, not receiver behaviour (the job has its own suite).
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Http::fake(['*' => Http::response('ok', 200)]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Emit a delivery when an Admin creates a User.
     */
    #[Test]
    public function it_emits_a_delivery_when_a_user_is_created(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::UserCreated->value]]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Emitted',
            'email' => 'emitted@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();

        $this->assertDatabaseHas('webhook_deliveries', ['event' => WebhookEvent::UserCreated->value]);
    }

    /**
     * Emit a delivery when an Admin suspends a User.
     */
    #[Test]
    public function it_emits_a_delivery_when_a_user_is_suspended(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::UserSuspended->value]]);

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$member->id}/suspend")->assertOk();

        // Assert

        $this->assertDatabaseHas('webhook_deliveries', ['event' => WebhookEvent::UserSuspended->value]);
    }

    /**
     * Emit a delivery when an Admin creates a Team.
     */
    #[Test]
    public function it_emits_a_delivery_when_a_team_is_created(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::TeamCreated->value]]);

        // Act

        $this->actingAs($admin)->postJson('/api/teams', ['name' => 'Emitted Team'])->assertCreated();

        // Assert

        $this->assertDatabaseHas('webhook_deliveries', ['event' => WebhookEvent::TeamCreated->value]);
    }

    /**
     * Emit nothing when no endpoint subscribes to the event.
     */
    #[Test]
    public function it_emits_nothing_without_a_subscriber(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::TeamCreated->value]]);

        // Act

        /** @var Team $team */
        $team = Team::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/teams/{$team->id}")->assertOk();

        // Assert

        $this->assertDatabaseEmpty('webhook_deliveries');
    }

    /**
     * Emit a delivery when a User self-registers through the public endpoint.
     */
    #[Test]
    public function it_emits_a_delivery_when_a_user_self_registers(): void
    {
        // Arrange

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::UserCreated->value]]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Self Registered',
            'email' => 'self-registered@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();

        $this->assertDatabaseHas('webhook_deliveries', ['event' => WebhookEvent::UserCreated->value]);
    }

    /**
     * Emit a delivery when an Admin deletes a User.
     */
    #[Test]
    public function it_emits_a_delivery_when_a_user_is_deleted(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $member */
        $member = User::factory()->user()->create();

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::UserDeleted->value]]);

        // Act

        $this->actingAs($admin)->deleteJson("/api/users/{$member->id}")->assertOk();

        // Assert

        $this->assertDatabaseHas('webhook_deliveries', ['event' => WebhookEvent::UserDeleted->value]);
    }

    /**
     * Emit a delivery when an Admin renames a Team.
     */
    #[Test]
    public function it_emits_a_delivery_when_a_team_is_renamed(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Before Rename']);

        WebhookEndpoint::factory()->create(['events' => [WebhookEvent::TeamUpdated->value]]);

        // Act

        $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", ['name' => 'After Rename'])->assertOk();

        // Assert

        $this->assertDatabaseHas('webhook_deliveries', ['event' => WebhookEvent::TeamUpdated->value]);
    }
}
