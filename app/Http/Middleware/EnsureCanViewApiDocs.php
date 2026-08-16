<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optionally protects API documentation routes with HTTP Basic Auth.
 *
 * When `API_DOCS_BASIC_AUTH_USER` and `API_DOCS_BASIC_AUTH_PASSWORD` are unset,
 * documentation is public in every environment.
 */
final class EnsureCanViewApiDocs
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Handle an incoming request.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the docs response, or a 401 challenge when basic auth fails
     */
    public function handle(Request $request, Closure $next): Response
    {
        $credentials = $this->configuredBasicAuth();

        if ($credentials === null) {
            return $next($request);
        }

        if ($this->credentialsMatch(
            request: $request,
            expectedUser: $credentials['user'],
            expectedPassword: $credentials['password'],
        )) {
            return $next($request);
        }

        return response('Unauthorized', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="API Docs", charset="UTF-8"',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Return configured basic-auth credentials when both values are non-empty.
     *
     * @return array{user: string, password: string}|null
     */
    private function configuredBasicAuth(): ?array
    {
        $user = config('api.docs_basic_auth.user');
        $password = config('api.docs_basic_auth.password');

        if (! is_string($user) || $user === '' || ! is_string($password) || $password === '') {
            return null;
        }

        return [
            'user' => $user,
            'password' => $password,
        ];
    }

    /**
     * Compare the request credentials to the configured values in constant time.
     *
     * @param  Request $request          the incoming request carrying basic-auth credentials
     * @param  string  $expectedUser     the configured docs username
     * @param  string  $expectedPassword the configured docs password
     * @return bool    true when both the username and password match
     */
    private function credentialsMatch(
        Request $request,
        string $expectedUser,
        string $expectedPassword,
    ): bool {
        $providedUser = $request->getUser();
        $providedPassword = $request->getPassword();

        if (! is_string($providedUser) || ! is_string($providedPassword)) {
            return false;
        }

        return hash_equals($expectedUser, $providedUser)
            && hash_equals($expectedPassword, $providedPassword);
    }
}
