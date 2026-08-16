<?php

declare(strict_types=1);

use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'api-docs' => \App\Http\Middleware\EnsureCanViewApiDocs::class,
            'active.account' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'session.version' => \App\Http\Middleware\EnsureSessionVersionMatches::class,
        ]);

        $middleware->statefulApi();

        /*
         * API-only app — never redirect unauthenticated guests to a web login route.
         */
        $middleware->redirectGuestsTo(static fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionRenderer::register($exceptions);
    })->create();
