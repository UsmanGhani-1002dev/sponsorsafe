<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch N+1 queries while developing and testing: a lazy load in a loop throws.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
