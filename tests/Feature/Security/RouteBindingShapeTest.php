<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A malformed id must not match an id-bound route at all. MySQL coerces a string
 * with a numeric prefix, so `7abc`, `07`, and `7e0` would otherwise resolve row 7
 * through the binder: a malformed URL would answer for a real row instead of
 * `404`, and the `403`-versus-`404` difference maps ids to rows for a caller who
 * may not read them.
 */
#[CoversNothing]
final class RouteBindingShapeTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Every id-bound route rejects a malformed id, on reads and on writes.
     *
     * @param  string $id the malformed id
     * @return void
     */
    #[Test]
    #[DataProvider('malformedIds')]
    public function it_does_not_match_an_id_bound_route_for_a_malformed_id(string $id): void
    {
        // Arrange

        $idBoundUris = [
            'api/users/{user}',
            'api/sessions/{web_session}',
            'api/tokens/{token}',
            'api/clients/{client}',
            'api/audit-logs/{auth_audit_log}',
            'api/roles/{role}',
            'api/webhook-endpoints/{webhook_endpoint}',
            'api/teams/{team}',
            'api/auth/email/verify/{id}/{hash}',
        ];

        $requests = [
            Request::create('/api/users/'.$id, 'GET'),
            Request::create('/api/users/'.$id, 'PATCH'),
            Request::create('/api/users/'.$id, 'DELETE'),
            Request::create('/api/sessions/'.$id, 'GET'),
            Request::create('/api/tokens/'.$id, 'DELETE'),
            Request::create('/api/clients/'.$id, 'GET'),
            Request::create('/api/audit-logs/'.$id, 'GET'),
            Request::create('/api/roles/'.$id, 'GET'),
            Request::create('/api/webhook-endpoints/'.$id, 'GET'),
            Request::create('/api/teams/'.$id, 'GET'),
            Request::create('/api/auth/email/verify/'.$id.'/'.sha1('shape-probe'), 'GET'),
        ];

        // Act

        $matched = [];

        foreach ($requests as $request) {
            foreach (Route::getRoutes()->getRoutes() as $route) {
                /** @var RoutingRoute $route */
                if (in_array($route->uri(), $idBoundUris, true) && $route->matches($request)) {
                    $matched[] = $request->getMethod().' /'.$route->uri();
                }
            }
        }

        // Assert

        $this->assertSame(
            [],
            array_values(array_unique($matched)),
            'A malformed id must not match an id-bound route; it should fall through to a 404.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Ids that must never reach an id-bound controller.
     *
     * @return array<string, array{string}>
     */
    public static function malformedIds(): array
    {
        return [
            'alpha suffix' => ['7abc'],
            'leading zero' => ['07'],
            'exponent' => ['7e0'],
        ];
    }
}
