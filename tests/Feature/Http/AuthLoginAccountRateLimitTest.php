<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Providers\AppServiceProvider;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the address-independent per-account login budget.
 *
 * The limiter is exercised as registered in {@see AppServiceProvider} - only the
 * allowance is tightened through config - so the tests cover the real
 * composite + per-account + per-IP shape rather than a test-only stand-in.
 */
#[CoversClass(ApiResponse::class)]
#[CoversClass(AppServiceProvider::class)]
final class AuthLoginAccountRateLimitTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed the role matrix, matching the other auth rate-limit suites.
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
     * Spend one account's budget across two addresses, so rotating addresses
     * cannot buy fresh attempts.
     */
    #[Test]
    public function it_bounds_one_account_across_different_addresses(): void
    {
        // Arrange

        config(['api.auth_login_account_rate_limit_per_minute' => 2]);

        $payload = [
            'email' => 'shared@example.com',
            'password' => 'WrongPass1',
        ];

        // Act

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/auth/login', $payload)
            ->assertUnprocessable();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/auth/login', $payload)
            ->assertUnprocessable();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/auth/login', $payload);

        // Assert

        $this->assertApiErrorEnvelope($response, 429, 'Too Many Requests');

        /*
         * The second address's own composite bucket is untouched, which is what
         * proves the rejection came from the shared account budget rather than
         * the per-address one: a different account from that address still
         * reaches validation.
         */
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/auth/login', ['email' => 'other@example.com', 'password' => 'WrongPass1'])
            ->assertUnprocessable();
    }

    /**
     * Bound one account short of its neighbours: a different account from the
     * same address keeps its own budget.
     */
    #[Test]
    public function it_bounds_only_the_account_it_was_spent_on(): void
    {
        // Arrange

        config(['api.auth_login_account_rate_limit_per_minute' => 1]);

        // Act

        $this->postJson('/api/auth/login', ['email' => 'first@example.com', 'password' => 'WrongPass1'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'first@example.com', 'password' => 'WrongPass1'])
            ->assertStatus(429);

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', ['email' => 'second@example.com', 'password' => 'WrongPass1']);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Leave the remember-me restore out of the per-account bucket: it carries
     * no e-mail, so keying it would drop every restore into one shared bucket.
     */
    #[Test]
    public function it_does_not_apply_the_account_budget_without_an_email(): void
    {
        // Arrange

        config(['api.auth_login_account_rate_limit_per_minute' => 1]);

        $this->postJson('/api/auth/login', ['email' => 'bucket@example.com', 'password' => 'WrongPass1'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'bucket@example.com', 'password' => 'WrongPass1'])
            ->assertStatus(429);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login/remember');

        // Assert

        $response->assertStatus(401);
    }
}
