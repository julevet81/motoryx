<?php

namespace App\Providers;

use App\Services\CarService;
use App\Services\NotificationService;
use App\Services\NumberGeneratorService;
use App\Services\SaleService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind services as singletons (shared instance per request cycle)
        $this->app->singleton(NumberGeneratorService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(CarService::class);

        $this->app->singleton(
            SaleService::class,
            fn($app) =>
            new SaleService($app->make(NumberGeneratorService::class))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Log slow queries (> 1 second) in production
        if ($this->app->environment('production')) {
            DB::whenQueryingForLongerThan(1000, function () {
                Log::warning('Slow query detected', [
                    'queries' => DB::getQueryLog(),
                ]);
            });
        }

        // Prevent mass-assignment silent failure
        \Illuminate\Database\Eloquent\Model::preventSilentlyDiscardingAttributes(
            ! $this->app->environment('production')
        );

        Schema::defaultStringLength(191);
    }
}