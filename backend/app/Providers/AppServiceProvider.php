<?php

namespace App\Providers;

use App\Http\Controllers\Api\AnalyticsController;
use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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

        // Dashboard numbers are cached; clear them whenever the data they
        // are computed from changes, so the cache never serves stale KPIs.
        $forgetSummary = fn () => Cache::forget(AnalyticsController::SUMMARY_CACHE_KEY);

        foreach ([ServiceRequest::class, Review::class] as $model) {
            $model::saved($forgetSummary);
            $model::deleted($forgetSummary);
        }
    }
}
