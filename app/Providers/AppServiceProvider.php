<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure rate limiters for GPS tracking
        RateLimiter::for('gps', function ($request) {
            $maxAttempts = config('gps.rate_limit.max_attempts', 120);

            return $request->user()
                ? Limit::perMinute($maxAttempts)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        // Configure general API rate limiter
        RateLimiter::for('api', function ($request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });
    }
}
