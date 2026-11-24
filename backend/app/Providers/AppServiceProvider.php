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
        // Outside production, a lazy-loaded relation throws instead of
        // silently running one query per row (N+1). Fix by eager loading
        // with with()/load() where the relation is used.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
