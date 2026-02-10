<?php

namespace App\Observers;

use App\Models\Ad;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class AdObserver
{
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the Ad "created" event.
     * 
     * When a new ad is created:
     * 1. Index it in Elasticsearch
     * 2. Invalidate relevant search caches
     */
    public function created(Ad $ad): void
    {
        // 1. Index in Elasticsearch
        $this->indexInElasticsearch($ad);

        // 2. Invalidate search caches
        $this->invalidateSearchCaches($ad, 'created');
    }

    /**
     * Handle the Ad "updated" event.
     * 
     * When an ad is updated:
     * 1. Re-index in Elasticsearch
     * 2. Invalidate caches (both old and new data)
     */
    public function updated(Ad $ad): void
    {
        // Get original values before update
        $original = $ad->getOriginal();

        // 1. Re-index in Elasticsearch
        $this->indexInElasticsearch($ad);

        // 2. Invalidate caches for both old and new data
        // (in case country_iso, keywords, or dates changed)
        $this->invalidateSearchCaches($ad, 'updated', $original);
    }

    /**
     * Handle the Ad "deleted" event.
     * 
     * When an ad is deleted:
     * 1. Remove from Elasticsearch
     * 2. Invalidate search caches
     */
    public function deleted(Ad $ad): void
    {
        // 1. Remove from Elasticsearch
        $this->removeFromElasticsearch($ad);

        // 2. Invalidate search caches
        $this->invalidateSearchCaches($ad, 'deleted');
    }

    /**
     * Handle the Ad "restored" event (soft deletes).
     */
    public function restored(Ad $ad): void
    {
        // Same as created
        $this->indexInElasticsearch($ad);
        $this->invalidateSearchCaches($ad, 'restored');
    }

    /**
     * Index ad in Elasticsearch
     */
    private function indexInElasticsearch(Ad $ad): void
    {
        try {
            $client = app('elasticsearch');
            $indexName = config('elasticsearch.indices.ads.name');

            // Prepare document for indexing
            $document = [
                'id' => $ad->id,
                'brand_id' => $ad->brand_id,
                'brand_name' => $ad->brand?->name ?? 'Unknown',
                'title' => $ad->title,
                'keywords' => $ad->keywords,
                'country_iso' => $ad->country_iso,
                'start_date' => $ad->start_date,
                'relevance_score' => $ad->relevance_score,
                'created_at' => $ad->created_at?->toIso8601String(),
            ];

            // Index document
            $client->index([
                'index' => $indexName,
                'id' => $ad->id,
                'body' => $document
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to index ad in Elasticsearch', [
                'ad_id' => $ad->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw - allow the model operation to complete
            // Elasticsearch indexing is secondary to database persistence
        }
    }

    /**
     * Remove ad from Elasticsearch
     */
    private function removeFromElasticsearch(Ad $ad): void
    {
        try {
            $client = app('elasticsearch');
            $indexName = config('elasticsearch.indices.ads.name');

            $client->delete([
                'index' => $indexName,
                'id' => $ad->id
            ]);

        } catch (\Exception $e) {
            // If document doesn't exist, that's fine (might not have been indexed yet)
            if (strpos($e->getMessage(), 'document_missing_exception') === false) {
                Log::error('Failed to remove ad from Elasticsearch', [
                    'ad_id' => $ad->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Invalidate search caches affected by this ad change
     * 
     * @param Ad $ad The ad that changed
     * @param string $event Event type (created, updated, deleted)
     * @param array|null $original Original values (for updates)
     */
    private function invalidateSearchCaches(Ad $ad, string $event, ?array $original = null): void
    {
        try {
            // Prepare current ad data
            $adData = [
                'id' => $ad->id,
                'brand_id' => $ad->brand_id,
                'title' => $ad->title,
                'keywords' => $ad->keywords,
                'country_iso' => $ad->country_iso,
                'start_date' => $ad->start_date,
            ];

            // Invalidate for current data
            $this->cacheService->invalidateByAd($adData);

            // For updates, also invalidate old data
            // (in case country_iso or keywords changed)
            if ($event === 'updated' && $original) {
                $originalData = [
                    'id' => $ad->id,
                    'brand_id' => $original['brand_id'] ?? $ad->brand_id,
                    'title' => $original['title'] ?? $ad->title,
                    'keywords' => $original['keywords'] ?? $ad->keywords,
                    'country_iso' => $original['country_iso'] ?? $ad->country_iso,
                    'start_date' => $original['start_date'] ?? $ad->start_date,
                ];

                // Only invalidate if something relevant changed
                if ($originalData !== $adData) {
                    $this->cacheService->invalidateByAd($originalData);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate search caches', [
                'ad_id' => $ad->id,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }
}