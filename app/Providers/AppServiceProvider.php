<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('login', function (Request $request) {
            $nip = $request->input('nip');
            $identity = hash('sha256', $request->ip().'|'.(is_string($nip) ? $nip : ''));

            return [
                Limit::perMinute(5)->by($identity),
                Limit::perMinute(30)->by((string) $request->ip()),
            ];
        });
    }
}
