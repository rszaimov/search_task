<?php

namespace App\Observers;

use App\Models\Brand;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

class BrandObserver
{
    /**
     * Handle the Brand "created" event.
     */
    public function created(Brand $brand): void
    {
        $this->invalidateCache($brand, 'created');
    }

    /**
     * Handle the Brand "updated" event.
     */
    public function updated(Brand $brand): void
    {
        // Only invalidate if ad_limit changed
        if ($brand->wasChanged('ad_limit')) {
            $this->invalidateCache($brand, 'updated');
        }
    }

    /**
     * Handle the Brand "deleted" event.
     */
    public function deleted(Brand $brand): void
    {
        $this->invalidateCache($brand, 'deleted');
    }

    /**
     * Invalidate brand limits cache
     */
    private function invalidateCache(Brand $brand, string $event): void
    {
        Log::info('Brand limits changed, invalidating cache', [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'event' => $event,
            'old_limit' => $brand->getOriginal('ad_limit'),
            'new_limit' => $brand->ad_limit,
        ]);

        app(SearchService::class)->invalidateBrandLimitsCache();
    }
}