<?php

declare(strict_types=1);

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
        // Behind nginx, `fastcgi_param HTTP_HOST $host` drops the port, so the
        // request root Laravel derives is portless (http://localhost). In
        // production-build mode that makes @vite and Ziggy emit asset/route URLs
        // on the wrong port, leaving the browser with a blank page. Pin every
        // generated URL to the 12-factor APP_URL instead of the mangled request.
        $appUrl = (string) config('app.url');

        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);

            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
