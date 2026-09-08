<?php

declare(strict_types=1);

namespace App\Http\Controllers\CspReports;

use App\Actions\CspReports\LogCspReportAction;
use App\Http\Requests\CspReports\StoreCspReportRequest;
use App\Services\CspReports\CspReportPayloadParser;
use Illuminate\Http\Response;

/**
 * Accepts a browser-submitted CSP violation report and logs it.
 *
 * Public and unauthenticated - the caller is an anonymous browser enforcing
 * the CSP, not a signed-in User - and rate-limited (`throttle:csp-reports`)
 * rather than gated on identity. See routes/api.php `csp-reports.store`.
 */
final class StoreCspReportController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Log a CSP violation report and acknowledge with no body.
     *
     * @param  StoreCspReportRequest  $request the (unvalidated-input) CSP report request
     * @param  CspReportPayloadParser $parser  the raw-body parser for both reporting shapes
     * @param  LogCspReportAction     $action  the Action that writes to the `csp-reports` channel
     * @return Response               a bodyless acknowledgement; never the ApiResponse envelope
     */
    public function __invoke(
        StoreCspReportRequest $request,
        CspReportPayloadParser $parser,
        LogCspReportAction $action,
    ): Response {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        |
        | Browsers send `application/csp-report` (legacy report-uri) or
        | `application/reports+json` (modern report-to), neither of which
        | Laravel treats as form data - `$request->all()` would be empty - so
        | the raw body is read and decoded directly. The size guard runs
        | before any decoding: an anonymous, unauthenticated caller must never
        | be able to make this endpoint burn CPU on a huge JSON payload or
        | fill the `csp-reports` log file via an oversized POST.
        |
        */

        $rawBody = $request->getContent();

        if ($parser->exceedsMaxBodySize($rawBody)) {
            return response()->noContent(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $contentType = $request->headers->get('Content-Type') ?? '';
        $reports = $parser->parse($contentType, $rawBody);
        $action->execute($reports);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        |
        | A browser discards the response body of a CSP report POST and never
        | retries, so this always acknowledges with 204 - including malformed
        | or empty JSON - rather than surfacing a client error for input the
        | caller cannot meaningfully react to.
        |
        */

        return response()->noContent();
    }
}
