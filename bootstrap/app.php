<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            '/logout',
            '/reset-session',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
