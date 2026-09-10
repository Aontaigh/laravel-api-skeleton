<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Request correlation IDs (`X-Request-ID`).
 *
 * Industry convention (Stripe `Request-Id`, AWS `x-amzn-RequestId`, GitHub
 * `x-github-request-id`): every response carries the ID so clients, logs, and
 * audit rows can be joined on one value. An inbound ID is honoured when it is
 * well-formed; anything else is replaced with a fresh UUID so attacker
 * input can never flow verbatim into log lines or response headers.
 *
 * A W3C `traceparent` header is accepted as a fallback source (its trace ID
 * becomes the request ID), giving single-request debugging today and
 * distributed-tracing interop later without changing the contract. The ID is
 * correlation metadata only - never authentication, never authorisation.
 */
final class RequestId
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Inbound header carrying a caller-supplied correlation ID. */
    private const string HEADER = 'X-Request-ID';

    /** Fallback inbound header (W3C Trace Context) when no request ID is sent. */
    private const string TRACEPARENT_HEADER = 'traceparent';

    /**
     * Longest inbound ID honoured before a fresh one is generated.
     *
     * Correlation IDs are short opaque tokens; anything longer is either a
     * mistake or log-bloat abuse.
    /**
     * Longest inbound ID honoured before a fresh one is generated.
     * Correlation IDs are short opaque tokens; anything longer is either a
     * mistake or log-bloat abuse.
     */
    private const int MAX_LENGTH = 128;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the correlation ID for the request, generating one when needed.
     *
     * Prefers a well-formed inbound `X-Request-ID`, then the trace ID of a
     * well-formed `traceparent`, then a fresh UUID. Always returns a value
     * safe for headers, logs, and database columns.
     *
     * @param  Request $request the inbound HTTP request
     * @return string  the correlation ID for this request
     */
    public static function current(Request $request): string
    {
        $header = $request->headers->get(self::HEADER);

        if (is_string($header) && self::isValid($header)) {
            return $header;
        }

        $traceId = self::traceId($request);

        if ($traceId !== null) {
            return $traceId;
        }

        return Str::uuid()->toString();
    }

    /**
     * Whether the value is safe to echo into headers, logs, and columns.
     *
     * Restricted to RFC 3986 unreserved characters plus hyphen so the value
     * can never carry newlines, quotes, or control characters into a sink.
     *
     * @param  string $value the candidate correlation ID
     * @return bool   true when the value may be honoured as-is
     */
    public static function isValid(string $value): bool
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/\A[A-Za-z0-9._~-]+\z/', $value) === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Extract the trace ID from a W3C `traceparent` header, if well-formed.
     *
     * Accepts `version-traceid-parentid-flags` with a non-zero trace ID and
     * rejects future versions that change the layout (`version` starting with
     * `ff` is invalid per the spec, and unknown versions must not be parsed
     * beyond the fields this code understands).
     *
     * @param  Request     $request the inbound HTTP request
     * @return string|null the 32-hex trace ID, or null when absent or malformed
     */
    private static function traceId(Request $request): ?string
    {
        $header = $request->headers->get(self::TRACEPARENT_HEADER);

        if (! is_string($header)) {
            return null;
        }

        $parts = explode('-', $header);

        if (count($parts) !== 4) {
            return null;
        }

        [$version, $traceId] = $parts;

        if (preg_match('/\A[0-9a-f]{2}\z/', $version) !== 1 || $version === 'ff') {
            return null;
        }

        if (preg_match('/\A[0-9a-f]{32}\z/', $traceId) !== 1 || $traceId === str_repeat('0', 32)) {
            return null;
        }

        return $traceId;
    }
}
