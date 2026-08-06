<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\RoleName;
use App\Http\Controllers\Users\StoreUserTokenController;
use App\Http\Requests\Users\StoreUserTokenRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the admin-issued Token creation endpoint.
 */
#[CoversClass(StoreUserTokenController::class)]
#[CoversClass(StoreUserTokenRequest::class)]
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(CreateTokenData::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(PersonalAccessTokenPolicy::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
final class StoreUserTokenControllerTest extends TestCase
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
     * Allow an admin to create a Token for another User.
     */
    #[Test]
    public function it_allows_an_admin_to_create_a_token_for_another_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson(
            "/api/users/{$target->id}/tokens",
            ['name' => 'Integration Token'],
        );

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.token.name', 'Integration Token');
        $this->assertNotEmpty($response->json('data.plain_text_token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Integration Token',
            'tokenable_id' => $target->id,
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

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson(
            "/api/users/{$target->id}/tokens",
            ['name' => '<script>alert(1)</script>'],
        );

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.token.name', 'alert(1)');
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'alert(1)',
            'tokenable_id' => $target->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Reject unknown abilities for an admin-issued Token.
     */
    #[Test]
    public function it_rejects_unknown_abilities_for_an_admin_issued_token(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson(
            "/api/users/{$target->id}/tokens",
            [
                'name' => 'Bad Abilities',
                'abilities' => ['read'],
            ],
        );

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Invalid Token Abilities');
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Bad Abilities']);
    }

    /**
     * Deny non-admins from creating Tokens for other Users.
     */
    #[Test]
    /**
     * Deny non-admins from creating Tokens for other Users.
     */
    #[DataProvider('nonAdminRoleProvider')]
    public function it_denies_non_admins_from_creating_tokens_for_other_users(string $role): void
    {
        // Arrange

        $factory = match ($role) {
            RoleName::Manager->value => User::factory()->manager(),
            RoleName::User->value => User::factory()->user(),
            default => throw new InvalidArgumentException("Unmapped Role: {$role}"),
        };

        /** @var User $viewer */
        $viewer = $factory->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson(
            "/api/users/{$target->id}/tokens",
            ['name' => 'Integration Token'],
        );

        // Assert

        $response->assertForbidden();
    }

    /**
     * Return not found for a nonexistent target User.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_target_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson(
            '/api/users/999999/tokens',
            ['name' => 'Integration Token'],
        );

        // Assert

        $response->assertNotFound();
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Arrange

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson(
            "/api/users/{$target->id}/tokens",
            ['name' => 'Integration Token'],
        );

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Spatie roles that must not be able to issue a Token for another User.
     *
     * @return array<string, array{0: string}> case name mapped to [role]
     */
    public static function nonAdminRoleProvider(): array
    {
        return [
            'Manager' => [RoleName::Manager->value],
            'User' => [RoleName::User->value],
        ];
    }
}
