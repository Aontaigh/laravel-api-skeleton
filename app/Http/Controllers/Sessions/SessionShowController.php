<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Http\Requests\Sessions\SessionShowRequest;
use App\Http\Resources\WebSessionResource;
use App\Models\WebSession;
use App\Queries\IndexFieldsQuery;
use App\Queries\Sessions\SessionIncludeQuery;
use App\Queries\Sessions\SessionQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a single registered cookie-bound web session.
 *
 * @example
 * GET /api/sessions/{web_session}?include=user&fields[sessions]=id,device_name
 */
final class SessionShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new SessionShowController.
     *
     * @param IndexFieldsQuery    $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param SessionIncludeQuery $includeQuery composes validated includes onto any Web Session builder
     */
    public function __construct(
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly SessionIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the route-bound Web Session with optional sparse fieldsets.
     *
     * The scoped `{web_session}` binding already 404s rows outside the
     * viewer's scope and revoked rows stay invisible: the registry answers
     * for active sessions only, matching the index.
     *
     * @param  SessionShowRequest $request    the validated show request
     * @param  WebSession         $webSession the route-bound Web Session
     * @return JsonResponse       the standardised success envelope
     */
    public function __invoke(
        SessionShowRequest $request,
        WebSession $webSession,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $sessionFields = $request->sessionFields();
        $userFields = $request->sessionUserFields();
        $includes = $request->includes();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<WebSession> $query */
        $query = WebSession::query()
            ->whereKey($webSession->getKey())
            ->whereNull(SessionQueryConstraints::TABLE.'.revoked_at');

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $sessionFields,
            allowedFields: SessionQueryConstraints::ALLOWED_FIELDS,
            table: SessionQueryConstraints::TABLE,
            requiredColumns: SessionQueryConstraints::requiredSelectColumns(),
        );

        $this->includeQuery->apply(
            query: $query,
            includes: $includes,
            userFields: $userFields,
        );

        /** @var WebSession $loadedSession */
        $loadedSession = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new WebSessionResource($loadedSession),
            message: 'Session Retrieved Successfully',
        );
    }
}
