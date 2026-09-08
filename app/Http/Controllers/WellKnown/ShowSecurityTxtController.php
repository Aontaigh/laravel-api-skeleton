<?php

declare(strict_types=1);

namespace App\Http\Controllers\WellKnown;

use App\Http\Requests\WellKnown\ShowSecurityTxtRequest;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the RFC 9116 security.txt file for vulnerability disclosure.
 *
 * @example
 * GET /.well-known/security.txt
 */
final class ShowSecurityTxtController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the security.txt document.
     *
     * @param  ShowSecurityTxtRequest $request the empty security.txt request
     * @return BinaryFileResponse     the security.txt file response
     */
    public function __invoke(ShowSecurityTxtRequest $request): BinaryFileResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Path
        |--------------------------------------------------------------------------
        */

        $path = base_path(config()->string('security.security_txt'));

        if (! File::isFile($path)) {
            abort(Response::HTTP_NOT_FOUND, 'Security Contact File Not Found');
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->file($path, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
