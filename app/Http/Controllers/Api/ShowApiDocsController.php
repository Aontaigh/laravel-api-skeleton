<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ShowApiDocsRequest;
use Illuminate\View\View;

/**
 * Renders the Scalar API reference UI.
 *
 * @example
 * GET /api/docs
 */
final class ShowApiDocsController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Show the interactive API documentation page.
     *
     * @param  ShowApiDocsRequest $request the empty API docs request
     * @return View               the Scalar API reference Blade view
     */
    public function __invoke(ShowApiDocsRequest $request): View
    {
        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return view('api-docs');
    }
}
