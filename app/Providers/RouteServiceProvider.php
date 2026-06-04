<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // General API: 120 requests/minute per user or IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429));
        });

        // Auth endpoint: 6 login attempts per minute per IP
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(6)
                ->by($request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Too many login attempts. Try again in a minute.',
                ], 429));
        });
    }
}
