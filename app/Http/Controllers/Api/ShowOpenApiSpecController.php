<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ShowOpenApiSpecRequest;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the hand-written OpenAPI specification for Scalar and other tooling.
 *
 * @example
 * GET /api/openapi.yaml
 */
final class ShowOpenApiSpecController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the OpenAPI 3.1 YAML document.
     *
     * @param  ShowOpenApiSpecRequest $request the empty OpenAPI spec request
     * @return BinaryFileResponse     the OpenAPI specification file response
     */
    public function __invoke(ShowOpenApiSpecRequest $request): BinaryFileResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Path
        |--------------------------------------------------------------------------
        */

        $path = base_path(config()->string('api.openapi_spec'));

        if (! File::isFile($path)) {
            abort(Response::HTTP_NOT_FOUND, 'OpenAPI Specification Not Found');
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->file($path, [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
