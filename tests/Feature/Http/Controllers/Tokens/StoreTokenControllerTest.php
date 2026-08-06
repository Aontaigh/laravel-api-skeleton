<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Tokens;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Exceptions\InvalidTokenAbilitiesException;
use App\Http\Controllers\Tokens\StoreTokenController;
use App\Http\Requests\Tokens\StoreTokenRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Services\Permissions\PermissionAbilityCatalog;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the self-service Token creation endpoint.
 */
#[CoversClass(StoreTokenController::class)]
#[CoversClass(StoreTokenRequest::class)]
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(InvalidTokenAbilitiesException::class)]
#[CoversClass(CreateTokenData::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(PermissionAbilityCatalog::class)]
#[CoversClass(PersonalAccessTokenPolicy::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
final class StoreTokenControllerTest extends TestCase
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
     * Seed the roles and permissions every test authorises against.
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
     * Create a Token for the authenticated User.
     */
    #[Test]
    public function it_creates_a_token_for_the_authenticated_user(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', ['name' => 'CLI Token']);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('message', 'Token Created Successfully');
        $response->assertJsonPath('data.token.name', 'CLI Token');
        $this->assertNotEmpty($response->json('data.plain_text_token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'CLI Token',
            'tokenable_id' => $viewer->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Strip markup from the Token name.
     */
    #[Test]
    public function it_strips_markup_from_the_token_name(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', [
            'name' => '<script>alert(1)</script>',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.token.name', 'alert(1)');
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'alert(1)',
            'tokenable_id' => $viewer->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Default abilities to a wildcard when omitted.
     */
    #[Test]
    public function it_defaults_abilities_to_a_wildcard_when_omitted(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', ['name' => 'Default Abilities']);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.token.abilities', ['*']);
    }

    /**
     * Accept registered permission abilities.
     */
    #[Test]
    public function it_accepts_registered_permission_abilities(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', [
            'name' => 'Scoped Token',
            'abilities' => ['tokens.list-own'],
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.token.abilities', ['tokens.list-own']);
    }

    /**
     * Reject unknown abilities.
     */
    #[Test]
    public function it_rejects_unknown_abilities(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', [
            'name' => 'Bad Abilities',
            'abilities' => ['read'],
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Invalid Token Abilities');
        $response->assertJsonPath('meta.invalid_abilities', ['read']);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Bad Abilities']);
    }

    /**
     * Reject a missing name.
     */
    #[Test]
    public function it_rejects_a_missing_name(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', []);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['name']);
    }

    /**
     * Reject non-string abilities.
     */
    #[Test]
    public function it_rejects_non_string_abilities(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson('/api/tokens', [
            'name' => 'Bad Abilities',
            'abilities' => [123],
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['abilities.0']);
    }

    /**
     * Deny viewers without the create-own permission.
     */
    #[Test]
    public function it_denies_viewers_without_the_create_own_permission(): void
    {
        // Arrange

        /** @var User $roleless */
        $roleless = User::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($roleless)->postJson('/api/tokens', ['name' => 'CLI Token']);

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/tokens', ['name' => 'CLI Token']);

        // Assert

        $response->assertUnauthorized();
    }
}
