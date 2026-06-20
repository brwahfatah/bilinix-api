<?php

use App\Http\Middleware\ForceJsonMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__ . '/../routes/web.php',
        api:      __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Force JSON Accept header on every API request so Laravel always
        // returns JSON errors (auth, validation, model not found, etc.)
        $middleware->prependToGroup('api', ForceJsonMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Exception normalisation is handled in app/Exceptions/Handler.php
    })
    ->create();
