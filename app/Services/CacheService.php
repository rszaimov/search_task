<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Generate cache key for search results
     * 
     * Includes offset to allow caching of sequential navigation
     * while ensuring cache correctness.
     * 
     * @param string $keyword Search keyword
     * @param array $filters Filters (country_iso, start_date)
     * @param int $page Page number
     * @param int $perPage Results per page
     * @param int|null $offset Starting offset (from token or calculated)
     * @return string Cache key
     */
    public function getSearchCacheKey(
        string $keyword, 
        array $filters, 
        int $page, 
        int $perPage,
        ?int $offset = null
    ): string {
        // Normalize keyword (lowercase, trim)
        $normalizedKeyword = strtolower(trim($keyword));
        
        // Sort filters for consistent keys
        ksort($filters);
        
        // Create hash of search parameters
        $hash = md5(json_encode([
            'keyword' => $normalizedKeyword,
            'filters' => $filters,
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset
        ]));
        
        return "search:results:{$hash}";
    }
    
    /**
     * Get cache tags for a search query
     * 
     * Tags allow invalidating related cached searches when data changes
     * 
     * @param string $keyword Search keyword
     * @param array $filters Filters
     * @return array Cache tags
     */
    public function getSearchCacheTags(string $keyword, array $filters): array
    {
        $tags = ['search'];
        
        // Add keyword tag
        if ($keyword) {
            $normalizedKeyword = strtolower(trim($keyword));
            $tags[] = "search:keyword:{$normalizedKeyword}";
        }
        
        // Add country tag
        if (!empty($filters['country_iso'])) {
            $tags[] = "search:country_iso:{$filters['country_iso']}";
        }
        
        // Add date range tag (generalize to year-month)
        if (!empty($filters['start_date'])) {
            $yearMonth = substr($filters['start_date'], 0, 7); // YYYY-MM
            $tags[] = "search:date:{$yearMonth}";
        }
        
        return $tags;
    }
    
    /**
     * Get cached search results
     * 
     * @param string $keyword
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @param int|null $offset Starting offset
     * @return array|null Cached results or null if not found
     */
    public function getSearchResults(
        string $keyword, 
        array $filters, 
        int $page, 
        int $perPage,
        ?int $offset = null
    ): ?array {
        $key = $this->getSearchCacheKey($keyword, $filters, $page, $perPage, $offset);
        $tags = $this->getSearchCacheTags($keyword, $filters);
        try {
            $cached = Cache::tags($tags)->get($key);
            
            if ($cached) {
                Log::debug('Search cache hit', [
                    'keyword' => $keyword,
                    'page' => $page,
                    'offset' => $offset,
                    'key' => $key
                ]);
            }
            
            return $cached;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached search results', [
                'error' => $e->getMessage(),
                'key' => $key
            ]);
            
            return null;
        }
    }
    
    /**
     * Cache search results with tags
     * 
     * @param string $keyword
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @param array $results Results to cache
     * @param int|null $offset Starting offset
     * @param int $ttl Time to live in seconds
     * @return void
     */
    public function cacheSearchResults(
        string $keyword, 
        array $filters, 
        int $page, 
        int $perPage, 
        array $results,
        ?int $offset = null,
        int $ttl = null
    ): void {
        $key = $this->getSearchCacheKey($keyword, $filters, $page, $perPage, $offset);
        $tags = $this->getSearchCacheTags($keyword, $filters);
        $ttl = $ttl ?? config('search.cache_ttl', 300);
        
        try {
            Cache::tags($tags)->put($key, $results, $ttl);
            
            Log::debug('Search results cached', [
                'keyword' => $keyword,
                'page' => $page,
                'offset' => $offset,
                'key' => $key,
                'tags' => $tags,
                'ttl' => $ttl
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache search results', [
                'error' => $e->getMessage(),
                'key' => $key
            ]);
        }
    }
    
    /**
     * Invalidate all search caches
     * 
     * Use when you need to flush all cached searches
     */
    public function invalidateAllSearches(): void
    {
        try {
            Cache::tags(['search'])->flush();
            
            Log::info('All search caches invalidated');
        } catch (\Exception $e) {
            Log::error('Failed to invalidate search caches', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Invalidate searches for specific keyword
     * 
     * @param string $keyword
     */
    public function invalidateKeyword(string $keyword): void
    {
        $normalizedKeyword = strtolower(trim($keyword));
        $tag = "search:keyword:{$normalizedKeyword}";
        
        try {
            Cache::tags([$tag])->flush();
            
            Log::info('Search cache invalidated for keyword', [
                'keyword' => $keyword,
                'tag' => $tag
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate keyword cache', [
                'error' => $e->getMessage(),
                'keyword' => $keyword
            ]);
        }
    }
    
    /**
     * Invalidate searches for specific country
     * 
     * @param string $country_iso
     */
    public function invalidateCountry(string $country_iso): void
    {
        $tag = "search:country_iso:{$country_iso}";
        
        try {
            Cache::tags([$tag])->flush();
            
            Log::info('Search cache invalidated for country', [
                'country_iso' => $country_iso,
                'tag' => $tag
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate country cache', [
                'error' => $e->getMessage(),
                'country_iso' => $country_iso
            ]);
        }
    }
    
    /**
     * Invalidate searches by ad
     * 
     * When a specific ad changes, invalidate relevant searches
     * 
     * @param array $adData Ad data (country_iso, keywords, etc.)
     */
    public function invalidateByAd(array $adData): void
    {
        $tagsToInvalidate = ['search'];
        
        // Invalidate country-specific searches
        if (!empty($adData['country_iso'])) {
            $tagsToInvalidate[] = "search:country_iso:{$adData['country_iso']}";
        }
        
        // Invalidate date-specific searches
        if (!empty($adData['start_date'])) {
            $yearMonth = substr($adData['start_date'], 0, 7);
            $tagsToInvalidate[] = "search:date:{$yearMonth}";
        }
        
        // Invalidate keyword-related searches
        if (!empty($adData['title']) || !empty($adData['keywords'])) {
            // Extract significant words from title and keywords
            $words = $this->extractSignificantWords($adData);
            
            foreach ($words as $word) {
                $tagsToInvalidate[] = "search:keyword:{$word}";
            }
        }
        
        try {
            Cache::tags($tagsToInvalidate)->flush();
            
            Log::info('Search cache invalidated for ad', [
                'ad_id' => $adData['id'] ?? null,
                'tags' => $tagsToInvalidate
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate ad cache', [
                'error' => $e->getMessage(),
                'ad_id' => $adData['id'] ?? null
            ]);
        }
    }
    
    /**
     * Extract significant words from ad data for cache invalidation
     * 
     * @param array $adData
     * @return array Significant words (normalized)
     */
    private function extractSignificantWords(array $adData): array
    {
        $text = [];
        
        if (!empty($adData['title'])) {
            $text[] = $adData['title'];
        }
        
        if (!empty($adData['keywords'])) {
            $text[] = $adData['keywords'];
        }
        
        $combined = implode(' ', $text);
        
        // Normalize: lowercase, remove special chars
        $normalized = strtolower($combined);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
        
        // Split into words
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        
        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
        $words = array_diff($words, $stopWords);
        
        // Only keep words 3+ characters
        $words = array_filter($words, fn($word) => strlen($word) >= 3);
        
        // Remove duplicates
        $words = array_unique($words);
        
        // Limit to top 10 most relevant words (by length - longer = more specific)
        usort($words, fn($a, $b) => strlen($b) - strlen($a));
        $words = array_slice($words, 0, 10);
        
        return array_values($words);
    }
}