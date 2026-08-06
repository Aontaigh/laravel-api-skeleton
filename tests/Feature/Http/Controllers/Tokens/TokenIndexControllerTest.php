<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Tokens;

use App\DataTransferObjects\ListSort;
use App\DataTransferObjects\Tokens\TokenFilters;
use App\Http\Controllers\Tokens\TokenIndexController;
use App\Http\Requests\Tokens\TokenIndexRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Queries\ListFieldsQuery;
use App\Queries\ListSortQuery;
use App\Queries\Tokens\TokenFilterQuery;
use App\Queries\Tokens\TokenQueryConstraints;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use App\Support\CommaSeparatedList;
use App\Support\LikePattern;
use App\Support\QualifiedColumn;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Token Index Endpoint.
 */
#[CoversClass(TokenIndexController::class)]
#[CoversClass(TokenIndexRequest::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(PersonalAccessTokenPolicy::class)]
#[CoversClass(TokenFilterQuery::class)]
#[CoversClass(TokenQueryConstraints::class)]
#[CoversClass(TokenFilters::class)]
#[CoversClass(ListFieldsQuery::class)]
#[CoversClass(ListSortQuery::class)]
#[CoversClass(ListSort::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class TokenIndexControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var User a permissioned viewer with `tokens.list-own` */
    private User $viewer;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and create the shared viewer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->viewer = User::factory()->user()->create();
    }

    /**
     * Restore the global strict-mode flags so they do not leak into other suites.
     */
    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    /*
     * Listing and Scoping Tests
     * -------------------------
     */

    /**
     * Return only the viewer's own Tokens.
     */
    #[Test]
    public function it_returns_only_the_viewers_own_tokens(): void
    {
        // Arrange

        /** @var User $otherUser */
        $otherUser = User::factory()->user()->create();

        $this->viewer->createToken('My Token');
        $otherUser->createToken('Someone Elses Token');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Tokens Retrieved Successfully');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'My Token');
        $response->assertJsonPath('meta.pagination.total', 1);
    }

    /**
     * Never expose the plaintext Token value.
     */
    #[Test]
    public function it_never_exposes_the_plaintext_token_value(): void
    {
        // Arrange

        $this->viewer->createToken('My Token');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $tokenPayload */
        $tokenPayload = $response->json('data.0');

        $this->assertArrayNotHasKey('token', $tokenPayload);
        $this->assertArrayNotHasKey('plain_text_token', $tokenPayload);
    }

    /**
     * Filter Tokens by the search term.
     */
    #[Test]
    public function it_filters_by_search_term(): void
    {
        // Arrange

        $this->viewer->createToken('CLI Token');
        $this->viewer->createToken('Browser Session');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens?filter[search]=CLI');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'CLI Token');
    }

    /*
     * Pagination Tests
     * ----------------
     */

    /**
     * Return a later page with accurate pagination meta.
     */
    #[Test]
    public function it_returns_a_later_page_with_accurate_pagination_meta(): void
    {
        // Arrange

        $this->viewer->createToken('Alpha');
        $this->viewer->createToken('Bravo');
        $this->viewer->createToken('Charlie');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens?per_page=2&page=2&sort=name');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Charlie');
        $response->assertJsonPath('meta.pagination.current_page', 2);
        $response->assertJsonPath('meta.pagination.per_page', 2);
        $response->assertJsonPath('meta.pagination.total', 3);
        $response->assertJsonPath('meta.pagination.last_page', 2);
    }

    /*
     * Sorting Tests
     * -------------
     */

    /**
     * Apply the documented default sort when sort is omitted.
     */
    #[Test]
    public function it_applies_the_documented_default_sort_when_sort_is_omitted(): void
    {
        // Arrange

        $older = $this->viewer->createToken('Older Token');
        $newer = $this->viewer->createToken('Newer Token');
        $newer->accessToken->forceFill(['created_at' => now()->addMinute()])->save();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $older->accessToken->id);
        $response->assertJsonPath('data.1.id', $newer->accessToken->id);
    }

    /**
     * Sort ascending by name when requested.
     */
    #[Test]
    public function it_sorts_ascending_by_name_when_requested(): void
    {
        // Arrange

        $this->viewer->createToken('Charlie');
        $this->viewer->createToken('Alpha');
        $this->viewer->createToken('Bravo');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens?sort=name');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.*.name', ['Alpha', 'Bravo', 'Charlie']);
    }

    /*
     * Fields Tests
     * ------------
     */

    /**
     * Return only requested Token columns in sparse fieldsets.
     */
    #[Test]
    public function it_returns_only_requested_token_columns_in_sparse_fieldsets(): void
    {
        // Arrange

        $this->viewer->createToken('Sparse Token');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson('/api/tokens?fields[tokens]=id,name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $token */
        $token = $response->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($token));
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject out-of-allow-list query params.
     */
    #[Test]
    /**
     * Reject out-of-allow-list query params.
     */
    #[DataProvider('invalidQueryProvider')]
    public function it_rejects_out_of_allow_list_query_params(string $queryString, string $expectedErrorKey): void
    {
        // Arrange

        $this->viewer->createToken('My Token');

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->viewer)->getJson("/api/tokens?{$queryString}");

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny viewers without the list-own permission.
     */
    #[Test]
    public function it_denies_viewers_without_the_list_own_permission(): void
    {
        // Arrange

        /** @var User $roleless */
        $roleless = User::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($roleless)->getJson('/api/tokens');

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
        $response = $this->getJson('/api/tokens');

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Hostile and out-of-allow-list Query Params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=token', 'sort'],
            'unknown filter key' => ['filter[abilities]=*', 'filter.abilities'],
            'unknown sparse field' => ['fields[tokens]=id,token', 'fields.tokens'],
            'unknown fields resource' => ['fields[users]=id', 'fields.users'],
            'unknown include' => ['include=user', 'include'],
            'array-shaped sort' => ['sort[]=name', 'sort'],
            'array-shaped include' => ['include[]=user', 'include'],
            'array-shaped sparse field' => ['fields[tokens][]=id', 'fields.tokens'],
            'scalar filter container' => ['filter=name', 'filter'],
            'scalar fields container' => ['fields=name', 'fields'],
            'page size above the hard maximum' => [
                'per_page='.(TokenQueryConstraints::MAX_PER_PAGE + 1),
                'per_page',
            ],
            'page size below one' => ['per_page=0', 'per_page'],
            'page below one' => ['page=0', 'page'],
        ];
    }
}
