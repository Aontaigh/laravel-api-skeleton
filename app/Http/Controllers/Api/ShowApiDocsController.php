<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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
     * @return View the Scalar API reference Blade view
     */
    public function __invoke(): View
    {
        return view('api-docs');
    }
}
