<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // TLS is terminated at the reverse proxy, so the request that reaches
        // PHP-FPM is plain HTTP. When the app is configured with an HTTPS
        // APP_URL, generate every URL with the https scheme -- including
        // Livewire's update endpoint, which the browser would otherwise block
        // as mixed content.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
