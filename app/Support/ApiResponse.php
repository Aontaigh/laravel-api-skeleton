<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Standard API success and error envelope.
 */
final class ApiResponse
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a success JSON response.
     *
     * @param  mixed                $data       the response payload (Resource, collection, etc.)
     * @param  string               $message    a Title Case message with no trailing period
     * @param  array<string, mixed> $meta       metadata about the payload (e.g. pagination)
     * @param  int                  $statusCode the HTTP status code
     * @return JsonResponse         the wrapped JSON response
     */
    public static function success(
        mixed $data,
        string $message,
        array $meta = [],
        int $statusCode = 200,
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'status_code' => $statusCode,
            'message' => $message,
            'data' => $data,
            'meta' => $meta === [] ? (object) [] : $meta,
        ], $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string               $message    a Title Case message with no trailing period
     * @param  int                  $statusCode the HTTP status code
     * @param  array<string, mixed> $meta       metadata about the failure (e.g. error code)
     * @return JsonResponse         the wrapped JSON response
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'status_code' => $statusCode,
            'message' => $message,
            'data' => null,
            'meta' => $meta === [] ? (object) [] : $meta,
        ], $statusCode);
    }

    /**
     * Return a validation error JSON response in the standard envelope.
     *
     * @param  ValidationException         $exception the failed validation exception
     * @param  array<string, list<string>> $allowed   allow-list hints keyed to the error field
     * @return JsonResponse                the wrapped JSON response
     */
    public static function validationError(
        ValidationException $exception,
        array $allowed = [],
    ): JsonResponse {
        $meta = ['errors' => $exception->errors()];

        if ($allowed !== []) {
            $meta['allowed'] = $allowed;
        }

        return self::error(
            message: 'Validation Failed',
            statusCode: 422,
            meta: $meta,
        );
    }

    /**
     * Build pagination metadata for the response envelope.
     *
     * The method is generic in both paginator templates because
     * `LengthAwarePaginator`'s `TValue` is invariant: a fixed
     * `LengthAwarePaginator<int, mixed>` parameter makes every concrete
     * `LengthAwarePaginator<int, User>` a caller actually holds a
     * PHPStan error, the same reasoning as the generic `{...}Query`
     * classes use for `Builder<TModel>`.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param  LengthAwarePaginator<TKey, TValue>                                  $paginator the paginator instance
     * @return array{current_page: int, per_page: int, total: int, last_page: int} pagination meta
     */
    public static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
