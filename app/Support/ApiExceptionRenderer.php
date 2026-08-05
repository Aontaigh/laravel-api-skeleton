<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InvalidTokenAbilitiesException;
use App\Services\Permissions\PermissionAbilityCatalog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Register API exception renderers on the application exception handler.
     *
     * @param Exceptions $exceptions the application exception configuration
     */
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(self::isApiRequest(...));

        $exceptions->render(self::renderInvalidTokenAbilities(...));
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
            default => $statusCode >= 500 ? 'Server Error' : 'Bad Request',
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
     * @param  ThrottleRequestsException $exception the throttle exception
     * @param  Request                   $request   the inbound request
     * @return JsonResponse|null         the envelope, or null when the request is not an API route
     */
    private static function renderThrottle(
        ThrottleRequestsException $exception,
        Request $request,
    ): ?JsonResponse {
        return self::envelope(
            request: $request,
            message: 'Too Many Requests',
            statusCode: 429,
        );
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
     * @param  NotFoundHttpException $exception the not-found exception
     * @param  Request               $request   the inbound request
     * @return JsonResponse|null     the envelope, or null when the request is not an API route
     */
    private static function renderNotFound(
        NotFoundHttpException $exception,
        Request $request,
    ): ?JsonResponse {
        return self::envelope(
            request: $request,
            message: 'Resource Not Found',
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
     * Catch-all for unexpected errors — never leak stack traces on API routes.
     *
     * @param  Throwable         $exception the uncaught throwable
     * @param  Request           $request   the inbound request
     * @return JsonResponse|null the envelope, or null when the request is not an API route
     */
    private static function renderThrowable(
        Throwable $exception,
        Request $request,
    ): ?JsonResponse {
        return self::envelope(
            request: $request,
            message: 'Server Error',
            statusCode: 500,
        );
    }
}
