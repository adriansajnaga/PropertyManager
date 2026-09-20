<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'approved' => App\Http\Middleware\EnsureUserIsApproved::class,
            'admin' => App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Każde zalogowane żądanie przechodzi przez sprawdzenie zatwierdzenia konta,
        // żeby żadna trasa (także z startera) nie została przypadkiem pominięta.
        $middleware->web(append: [
            App\Http\Middleware\EnsureUserIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
