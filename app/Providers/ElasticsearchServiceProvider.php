<?php

namespace App\Providers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('elasticsearch', function ($app) {
            $host = config('elasticsearch.host');
            $scheme = config('elasticsearch.scheme');
            
            return ClientBuilder::create()
                ->setHosts(["{$scheme}://{$host}"])
                ->build();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
