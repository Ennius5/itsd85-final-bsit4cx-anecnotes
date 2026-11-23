<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        // Anecdote ids are UUIDs; reject anything else before it reaches PostgreSQL.
        Route::pattern('anecdote', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
        Route::pattern('student', '[0-9]+');

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Keeps queued AI calls under the free-tier provider limit.
        RateLimiter::for('ai', fn () => Limit::perMinute((int) config('ai.max_requests_per_minute', 25)));

        // Swagger-style UI at /api/docs and the OpenAPI JSON at /api/docs.json (B7).
        Scramble::configure()
            ->expose(ui: 'api/docs', document: 'api/docs.json')
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }
}
