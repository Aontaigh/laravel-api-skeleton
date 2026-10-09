<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\Auth\OAuthTokenRequestException;
use App\Exceptions\InvalidTokenAbilitiesException;
use App\Exceptions\InvalidTokenExpirationException;
use App\Services\Permissions\PermissionAbilityCatalog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps every API-route exception to the standard ApiResponse error envelope.
 *
 * Docs routes (`/api/docs`, `/api/openapi.yaml`) are excluded so Scalar and the
 * raw OpenAPI file are not wrapped.
 */
final class ApiExceptionRenderer
{
    /**
     * The one endpoint that answers with the RFC 6749 body instead of the house envelope.
     *
     * A token endpoint is an interoperability surface owned by the spec, so its error shape is
     * fixed by [RFC 6749 section 5.2](https://www.rfc-editor.org/rfc/rfc6749#section-5.2) rather
     * than by house style. Scoping the exception to a single path keeps every other endpoint on
     * `{status, status_code, message, data, meta}`.
     */
    private const OAUTH_TOKEN_PATH = 'api/oauth/token';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Register API exception renderers on the application exception handler.
     *
     * @param  Exceptions $exceptions the application exception configuration
     * @return void
     */
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(self::isApiRequest(...));

        $exceptions->render(self::renderOAuthTokenRequest(...));
        $exceptions->render(self::renderInvalidTokenAbilities(...));
        $exceptions->render(self::renderInvalidTokenExpiration(...));
        $exceptions->render(self::renderAuthentication(...));
        $exceptions->render(self::renderValidation(...));
        $exceptions->render(self::renderThrottle(...));
        $exceptions->render(self::renderAccessDenied(...));
        $exceptions->render(self::renderNotFound(...));
        $exceptions->render(self::renderHttpException(...));
        $exceptions->render(self::renderThrowable(...));
    }

    /**
     * Whether the request should receive JSON API envelope responses.
     *
     * @param  Request $request the inbound request
     * @return bool    true when API routes (except docs) should use the envelope
     */
    public static function isApiRequest(Request $request): bool
    {
        if (! $request->is('api/*')) {
            return false;
        }

        return ! $request->is('api/docs', 'api/openapi.yaml');
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Title Case message for a given HTTP status code.
     *
     * Mapped codes carry house copy that deliberately diverges from the reason
     * phrase (`Unauthenticated`, `Validation Failed`). Unmapped 4xx fall back to
     * the official RFC 9110 reason phrase so the message can never contradict
     * `status_code`; unmapped 5xx stay generic, since phrases like `Bad Gateway`
     * leak infrastructure topology to clients.
     *
     * @param  int    $statusCode the HTTP status code
     * @return string the Title Case error message
     */
    protected static function messageForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Resource Not Found',
            405 => 'Method Not Allowed',
            422 => 'Validation Failed',
            429 => 'Too Many Requests',
            default => $statusCode >= 500
                ? 'Server Error'
                : (Response::$statusTexts[$statusCode] ?? 'Bad Request'),
        };
    }

    /**
     * Build an error envelope when the request targets the JSON API.
     *
     * @param  Request              $request    the inbound request
     * @param  string               $message    the Title Case error message
     * @param  int                  $statusCode the HTTP status code
     * @param  array<string, mixed> $meta       optional metadata for the envelope
     * @return JsonResponse|null    the envelope, or null when the request is not an API route
     */
    protected static function envelope(
        Request $request,
        string $message,
        int $statusCode,
        array $meta = [],
    ): ?JsonResponse {
        if (! self::isApiRequest($request)) {
            return null;
        }

        return ApiResponse::error(
            message: $message,
            statusCode: $statusCode,
            meta: $meta,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Render invalid Personal Access Token abilities as a validation-style envelope.
     *
     * @param  InvalidTokenAbilitiesException $exception the rejected abilities exception
     * @param  Request                        $request   the inbound request
     * @return JsonResponse|null              the envelope, or null when the request is not an API route
     */
    private static function renderInvalidTokenAbilities(
        InvalidTokenAbilitiesException $exception,
        Request $request,
    ): ?JsonResponse {
        /** @var PermissionAbilityCatalog $catalog */
        $catalog = app(PermissionAbilityCatalog::class);

        return self::envelope(
            request: $request,
            message: 'Invalid Token Abilities',
            statusCode: 422,
            meta: [
                'invalid_abilities' => $exception->invalidAbilities(),
                'allowed' => [
                    'abilities' => AllowListValidation::sorted([
                        '*',
                        ...$catalog->allNames(),
                    ]),
                ],
            ],
        );
    }

    /**
     * Render an invalid token expiry as a validation-style envelope.
     *
     * A token must expire and must not outlive the configured ceiling, so a
     * caller that asks for a non-expiring token or one beyond the maximum is
     * refused with the same `422` shape validation failures use. The ceiling is
     * echoed in `meta` so the caller can correct the request without guessing.
     *
     * @param  InvalidTokenExpirationException $exception the refused expiry exception
     * @param  Request                         $request   the inbound request
     * @return JsonResponse|null               the envelope, or null when the request is not an API route
     */
    private static function renderInvalidTokenExpiration(
        InvalidTokenExpirationException $exception,
        Request $request,
    ): ?JsonResponse {
        $meta = $exception->maximumDays === null
            ? []
            : ['max_expiration_days' => $exception->maximumDays];

        return self::envelope(
            request: $request,
            message: $exception->getMessage(),
            statusCode: 422,
            meta: $meta,
        );
    }

    /**
     * Render an authentication failure as the standard API envelope.
     *
     * @param  AuthenticationException $exception the authentication exception
     * @param  Request                 $request   the inbound request
     * @return JsonResponse|null       the envelope, or null when the request is not an API route
     */
    private static function renderAuthentication(
        AuthenticationException $exception,
        Request $request,
    ): ?JsonResponse {
        return self::envelope(
            request: $request,
            message: 'Unauthenticated',
            statusCode: 401,
        );
    }

    /**
     * Render an OAuth token-endpoint failure with the RFC 6749 wire shape.
     *
     * Standard: [RFC 6749 section 5.2](https://www.rfc-editor.org/rfc/rfc6749#section-5.2). The body
     * is a bare `{error, error_description}` object with `400`, deliberately **not** the
     * `{status, status_code, message, data, meta}` envelope every other endpoint uses.
     *
     * This is the one place interoperability outranks house style. Every conformant OAuth client
     * reads `error` at the top level to decide whether to retry, re-authenticate, or give up, so a
     * wrapped envelope makes the endpoint unusable with standard client libraries. Stripe answers
     * the same way: `{"error":"invalid_grant","error_description":"Authorization code does not
     * exist: ..."}` ([Connect OAuth reference](https://docs.stripe.com/connect/oauth-reference)).
     *
     * Scoped by route so no other endpoint can reach this shape, and the standard `400` is used
     * rather than `401`: RFC 6749 reserves `401` for a client that attempted authentication via the
     * `Authorization` header, and this endpoint reads credentials from the request body. The
     * `WWW-Authenticate` challenge is still emitted, which RFC 6750 section 3 requires on any
     * `401` and which advertises the `Bearer` scheme this API otherwise uses.
     *
     * @param  OAuthTokenRequestException $exception the OAuth failure carrying its wire code
     * @param  Request                    $request   the inbound request
     * @return JsonResponse|null          the RFC body, or null when the request is not the token endpoint
     */
    private static function renderOAuthTokenRequest(
        OAuthTokenRequestException $exception,
        Request $request,
    ): ?JsonResponse {
        if (! $request->is(self::OAUTH_TOKEN_PATH)) {
            return null;
        }

        return response()->json([
            'error' => $exception->errorCode,
            'error_description' => $exception->description,
        ], Response::HTTP_BAD_REQUEST, [
            'WWW-Authenticate' => 'Bearer realm="api", error="'.$exception->errorCode.'"',
        ]);
    }

    /**
     * Render a validation failure as the standard API envelope.
     *
     * @param  ValidationException $exception the failed validation exception
     * @param  Request             $request   the inbound request
     * @return JsonResponse|null   the envelope, or null when the request is not an API route
     */
    private static function renderValidation(
        ValidationException $exception,
        Request $request,
    ): ?JsonResponse {
        if (! self::isApiRequest($request)) {
            return null;
        }

        return ApiResponse::validationError($exception);
    }

    /**
     * Render a rate-limit failure as the standard API envelope.
     *
     * The framework builds the advisory headers on the exception - `Retry-After`
     * ([RFC 9110 section 10.2.3](https://www.rfc-editor.org/rfc/rfc9110#section-10.2.3))
     * plus the de-facto `X-RateLimit-Limit` / `X-RateLimit-Remaining` /
     * `X-RateLimit-Reset` set - and this renderer replaces the response they
     * would ride. Without copying them a throttled client has no interval to
     * wait and retries immediately, spending the next window as well
     * ([RFC 9110 section 15.5.30](https://www.rfc-editor.org/rfc/rfc9110#section-15.5.30)).
     * Values are cast to strings because the framework stores the attempt counts
     * as integers while `HeaderBag::set()` takes `string|array|null` under
     * strict types; a value that is neither a string nor an integer is not a
     * header and is dropped.
     *
     * @param  ThrottleRequestsException $exception the throttle exception carrying the headers
     * @param  Request                   $request   the inbound request
     * @return JsonResponse|null         the envelope, or null when the request is not an API route
     */
    private static function renderThrottle(
        ThrottleRequestsException $exception,
        Request $request,
    ): ?JsonResponse {
        $response = self::envelope(
            request: $request,
            message: 'Too Many Requests',
            statusCode: 429,
        );

        if ($response === null) {
            return null;
        }

        foreach ($exception->getHeaders() as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            /*
             * The framework sets these to the attempt counts (integers) and,
             * for a custom response callback, strings. Anything else is not a
             * header value and is dropped rather than cast.
             */
            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            $response->headers->set($name, (string) $value);
        }

        return $response;
    }

    /**
     * Render an authorisation failure as the standard API envelope.
     *
     * @param  AccessDeniedHttpException $exception the access-denied exception
     * @param  Request                   $request   the inbound request
     * @return JsonResponse|null         the envelope, or null when the request is not an API route
     */
    private static function renderAccessDenied(
        AccessDeniedHttpException $exception,
        Request $request,
    ): ?JsonResponse {
        return self::envelope(
            request: $request,
            message: 'Forbidden',
            statusCode: 403,
        );
    }

    /**
     * Render a not-found failure as the standard API envelope.
     *
     * An unmatched URI (typo'd path) answers "Route Not Found"; a missing
     * model or an explicit `abort(404)` on a matched route keeps "Resource
     * Not Found".
     *
     * @param  NotFoundHttpException $exception the not-found exception
     * @param  Request               $request   the inbound request
     * @return JsonResponse|null     the envelope, or null when the request is not an API route
     */
    private static function renderNotFound(
        NotFoundHttpException $exception,
        Request $request,
    ): ?JsonResponse {
        /*
         * Routing only binds a route to the request when the URI matched, so
         * route nullability separates the two 404 causes. This holds whether
         * the handler passes the raw ModelNotFoundException or the prepared
         * NotFoundHttpException to the render callbacks - that preparation
         * order has shifted between framework versions, and either way a
         * model miss happens after routing matched.
         */
        $message = $request->route() === null ? 'Route Not Found' : 'Resource Not Found';

        return self::envelope(
            request: $request,
            message: $message,
            statusCode: 404,
        );
    }

    /**
     * Catch remaining HTTP exceptions (e.g. 405 Method Not Allowed).
     *
     * @param  HttpException     $exception the HTTP exception
     * @param  Request           $request   the inbound request
     * @return JsonResponse|null the envelope, or null when the request is not an API route
     */
    private static function renderHttpException(
        HttpException $exception,
        Request $request,
    ): ?JsonResponse {
        $statusCode = $exception->getStatusCode();

        return self::envelope(
            request: $request,
            message: self::messageForStatusCode($statusCode),
            statusCode: $statusCode,
        );
    }

    /**
     * Catch-all for unexpected errors - never leak stack traces on API routes.
     *
     * @param  Throwable         $exception the uncaught throwable
     * @param  Request           $request   the inbound request
     * @return JsonResponse|null the envelope, or null when the request is not an API route
     */
    private static function renderThrowable(
        Throwable $exception,
        Request $request,
    ): ?JsonResponse {
        /*
         * A FormRequest failure throws HttpResponseException carrying an
         * already-prepared response (the 422 envelope from `failedValidation`).
         * The framework unwraps that after the render callbacks, so this
         * catch-all must decline it - matching it here would replace a
         * validation response with a 500.
         */
        if ($exception instanceof HttpResponseException) {
            return null;
        }

        return self::envelope(
            request: $request,
            message: 'Server Error',
            statusCode: 500,
        );
    }
}
