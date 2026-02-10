<?php

namespace App\Providers;

use App\Models\Ad;
use App\Models\Brand;
use App\Observers\BrandObserver;
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
        // Register observers
        Ad::observe(AdObserver::class);
        Brand::observe(BrandObserver::class);

    }
}
