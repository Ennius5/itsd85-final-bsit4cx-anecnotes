<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
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
        // Swagger-style UI at /api/docs and the OpenAPI JSON at /api/docs.json (B7).
        Scramble::configure()->expose(ui: 'api/docs', document: 'api/docs.json');
    }
}
