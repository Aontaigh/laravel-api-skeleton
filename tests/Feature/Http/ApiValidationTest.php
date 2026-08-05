<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Queries\Users\UserQueryConstraints;
use App\Support\AllowListValidation;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for API Validation Error Responses.
 */
#[CoversClass(ApiResponse::class)]
final class ApiValidationTest extends TestCase
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
     * Seed permissions and create an Admin viewer.
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

    #[Test]
    public function it_returns_the_standard_envelope_for_validation_failures(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users?per_page=999999');

        // Assert

        $this->assertApiValidationErrors($response, ['per_page']);
    }

    #[Test]
    public function it_returns_multiple_allow_list_errors_and_hints_in_one_response_on_show(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        $team = $admin->team;
        $this->assertNotNull($team);

        /** @var User $target */
        $target = User::factory()->for($team)->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/users/{$target->id}?include=roles&fields[users]=id,name,i&fields[roles]=id,bad",
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['include', 'fields.users', 'fields.roles']);

        $errors = $this->apiMetaErrors($response);
        $this->assertArrayHasKey('include', $errors);
        $this->assertArrayHasKey('fields.users', $errors);
        $this->assertArrayHasKey('fields.roles', $errors);

        $allowed = $this->apiMetaAllowed($response);
        $this->assertSame(['role', 'team'], $allowed['include'] ?? null);
        $this->assertSame(['created_at', 'email', 'id', 'name'], $allowed['fields.users'] ?? null);
        $this->assertSame(['created_at', 'id', 'name'], $allowed['fields.roles'] ?? null);

        $response->assertJsonFragment([
            'Unsupported Include: roles (Supported: role, team)',
        ]);
        $response->assertJsonFragment([
            'Unsupported Field: i (Supported: created_at, email, id, name)',
        ]);
        $response->assertJsonFragment([
            'Unsupported Field: bad (Supported: created_at, id, name)',
        ]);
    }

    #[Test]
    public function it_returns_multiple_allow_list_errors_and_hints_in_one_response_on_index(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/users?sort=bad&include=permissions&fields[users]=id,password&filter[team_id]=1',
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [
            'sort',
            'include',
            'fields.users',
            'filter.team_id',
        ]);

        $allowed = $this->apiMetaAllowed($response);
        $this->assertSame(AllowListValidation::sorted(UserQueryConstraints::ALLOWED_SORTS), $allowed['sort'] ?? null);
        $this->assertSame(AllowListValidation::sorted(UserQueryConstraints::ALLOWED_INCLUDES), $allowed['include'] ?? null);
        $this->assertSame(['created_at', 'email', 'id', 'name'], $allowed['fields.users'] ?? null);
        $this->assertSame(['search'], $allowed['filter'] ?? null);
    }
}
