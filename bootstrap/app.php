<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->statefulApi();

        // Configure rate limiters for GPS tracking
        RateLimiter::for('gps', function ($request) {
            $maxAttempts = config('gps.rate_limit.max_attempts', 120);
            $decaySeconds = config('gps.rate_limit.decay_seconds', 60);

            return $request->user()
                ? Limit::perMinute($maxAttempts)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api', function ($request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
