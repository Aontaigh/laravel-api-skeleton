<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Actions\Sessions\TouchWebSessionActivityAction;
use App\Http\Middleware\TouchWebSessionActivity;
use App\Models\User;
use App\Models\WebSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for the last-activity middleware wiring.
 */
#[CoversClass(TouchWebSessionActivity::class)]
#[CoversClass(TouchWebSessionActivityAction::class)]
final class TouchWebSessionActivityTest extends TestCase
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
     * Invoke the middleware with a fabricated request bound to a session id.
     *
     * Builds the request directly instead of going through the HTTP kernel so
     * the test pins the session id the registry row was created with.
     *
     * @param  User|null   $user        the resolved user, or null when unauthenticated
     * @param  string      $sessionId   the session id bound to the request
     * @param  string|null $bearerToken the bearer token, or null for a cookie session
     * @return Response    the downstream response
     */
    private function runMiddleware(?User $user, string $sessionId, ?string $bearerToken): Response
    {
        $store = $this->app->make('session.store');
        $store->setId($sessionId);

        $request = Request::create('/api/me', 'GET');
        $request->setLaravelSession($store);

        if ($bearerToken !== null) {
            $request->headers->set('Authorization', 'Bearer '.$bearerToken);
        }

        $request->setUserResolver(fn (): ?User => $user);

        $middleware = new TouchWebSessionActivity(new TouchWebSessionActivityAction);

        return $middleware->handle($request, fn (Request $req): Response => new Response('ok'));
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Touch the registry row bound to the inbound cookie session.
     */
    #[Test]
    public function it_touches_the_row_for_the_inbound_cookie_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $webSession = WebSession::factory()->for($user)->create([
            'last_activity_at' => now()->subMinutes(10),
        ]);

        $before = $webSession->last_activity_at;

        // Act

        $this->runMiddleware($user, $webSession->session_id, bearerToken: null);

        // Assert

        $fresh = $webSession->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertNotNull($webSession->last_activity_at);
        $this->assertNotNull($before);
        $this->assertTrue($fresh->last_activity_at->greaterThan($before));
    }

    /**
     * Skip the touch when the caller authenticated with a bearer token.
     */
    #[Test]
    public function it_skips_bearer_token_clients(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $webSession = WebSession::factory()->for($user)->create([
            'last_activity_at' => now()->subMinutes(10),
        ]);

        // Act

        $this->runMiddleware($user, $webSession->session_id, bearerToken: '1|bearer-token-value');

        // Assert

        $fresh = $webSession->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertNotNull($webSession->last_activity_at);
        $this->assertTrue($fresh->last_activity_at->equalTo($webSession->last_activity_at));
    }

    /**
     * Skip the touch when no user is resolved (unauthenticated request).
     */
    #[Test]
    public function it_skips_unauthenticated_requests(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $webSession = WebSession::factory()->for($user)->create([
            'last_activity_at' => now()->subMinutes(10),
        ]);

        // Act

        $this->runMiddleware(null, $webSession->session_id, bearerToken: null);

        // Assert

        $fresh = $webSession->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertNotNull($webSession->last_activity_at);
        $this->assertTrue($fresh->last_activity_at->equalTo($webSession->last_activity_at));
    }

    /**
     * Continue down the pipeline regardless of the touch outcome.
     */
    #[Test]
    public function it_continues_the_pipeline_for_an_unknown_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        $response = $this->runMiddleware($user, 'no-such-session-id', bearerToken: null);

        // Assert

        $this->assertSame('ok', $response->getContent());
    }
}
