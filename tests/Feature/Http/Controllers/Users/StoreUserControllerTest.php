<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\CreateUserAction;
use App\DataTransferObjects\Users\CreateUserData;
use App\Enums\MfaMethod;
use App\Enums\RoleName;
use App\Http\Controllers\Users\StoreUserController;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for admin user creation.
 */
#[CoversClass(StoreUserController::class)]
#[CoversClass(StoreUserRequest::class)]
#[CoversClass(CreateUserAction::class)]
#[CoversClass(CreateUserData::class)]
#[CoversClass(UserResource::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class StoreUserControllerTest extends TestCase
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
     * Seed roles and permissions.
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

    /*
     * Mutation Tests
     * --------------
     */

    /**
     * Create a user with the default User role.
     */
    #[Test]
    public function it_creates_a_user_with_the_default_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('message', 'User Created Successfully');
        $response->assertJsonPath('data.name', 'Alice');
        $response->assertJsonPath('data.email', 'alice@example.com');

        $this->assertDatabaseHas('users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $created = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole(RoleName::User));
        $this->assertNull($created->team_id);
        $this->assertNull($created->email_verified_at);
        $this->assertSame(MfaMethod::Email, $created->mfa_method);
    }

    /**
     * Store the email address in lowercase.
     */
    #[Test]
    public function it_stores_the_email_address_in_lowercase(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Alice',
            'email' => 'ALICE@EXAMPLE.COM',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'alice@example.com');
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    /**
     * Create a user with a specific role.
     */
    #[Test]
    public function it_creates_a_user_with_a_specific_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
            'role' => 'Manager',
        ]);

        // Assert

        $response->assertCreated();

        $created = User::query()->where('email', 'bob@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole(RoleName::Manager));
    }

    /**
     * Create a user assigned to a team.
     */
    #[Test]
    public function it_creates_a_user_with_a_team(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $team = Team::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
            'team_id' => $team->id,
        ]);

        // Assert

        $response->assertCreated();

        $created = User::query()->where('email', 'charlie@example.com')->firstOrFail();
        $this->assertSame($team->id, $created->team_id);
    }

    /**
     * No token is issued for admin-created users.
     */
    #[Test]
    public function it_does_not_issue_a_token_for_admin_created_users(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'No Token',
            'email' => 'notoken@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonMissingPath('data.token');
        $response->assertJsonMissingPath('data.plain_text_token');
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/users', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny non-admin callers.
     */
    #[Test]
    public function it_forbids_non_admin_callers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson('/api/users', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertForbidden();
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject invalid role names.
     */
    #[Test]
    public function it_rejects_an_invalid_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Bad Role',
            'email' => 'badrole@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
            'role' => 'SuperAdmin',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Reject the Service role for interactive users.
     */
    #[Test]
    public function it_rejects_the_service_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Service Account',
            'email' => 'service@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
            'role' => 'Service',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Reject a duplicate email.
     */
    #[Test]
    public function it_rejects_a_duplicate_email(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Reject missing required fields.
     *
     * @param array<string, mixed> $payload          the hostile request body
     * @param string               $expectedErrorKey the validation key that must error
     */
    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function it_rejects_invalid_payloads(array $payload, string $expectedErrorKey): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users', $payload);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Invalid create payloads mapped to the validation key that must error.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}> case name mapped to [payload, expectedErrorKey]
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'missing name' => [
                [
                    'email' => 'noname@example.com',
                    'password' => 'Xq7#mK2$vL9pTzW4',
                    'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
                ],
                'name',
            ],
            'missing email' => [
                [
                    'name' => 'No Email',
                    'password' => 'Xq7#mK2$vL9pTzW4',
                    'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
                ],
                'email',
            ],
            'missing password' => [
                [
                    'name' => 'No Password',
                    'email' => 'nopass@example.com',
                ],
                'password',
            ],
            'password confirmation mismatch' => [
                [
                    'name' => 'Mismatch',
                    'email' => 'mismatch@example.com',
                    'password' => 'Xq7#mK2$vL9pTzW4',
                    'password_confirmation' => 'DifferentPass12',
                ],
                'password',
            ],
        ];
    }
}
