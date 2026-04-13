<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        // CORS — orígenes permitidos (frontend Dash)
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->trustHosts(at: ['localhost', '127.0.0.1']);

        // Alias de middleware personalizados
        $middleware->alias([
            'role'        => \App\Http\Middleware\CheckRole::class,
            'imgbb.limit' => \App\Http\Middleware\CheckImgBBLimit::class,
            'auth.cuenta' => \App\Http\Middleware\AuthCuenta::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
