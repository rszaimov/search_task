<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchService
{
    private $client;
    private string $indexName;
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->client = app('elasticsearch');
        $this->indexName = config('elasticsearch.indices.ads.name');
        $this->cacheService = $cacheService;
    }

    /**
     * Search with page-based pagination and continuation tokens
     * 
     * Uses page offset map to track actual ending offsets per page,
     * eliminating duplicates caused by multi-iteration fetching.
     * 
     * @param string $keyword Search keyword (typo-tolerant)
     * @param array $filters Filters (country_iso, start_date)
     * @param int $page Page number (1-based)
     * @param string|null $continuationToken Opaque token from previous response
     * @param int $perPage Results per page
     * @return array Search results with continuation token
     */
    public function search(
        string $keyword,
        array $filters = [],
        int $page = 1,
        ?string $continuationToken = null,
        int $perPage = 20
    ): array {
        // Decode continuation token
        $state = $continuationToken 
            ? $this->decodeToken($continuationToken) 
            : ['offsets' => [], 'query_hash' => null];
        
        // Validate query hasn't changed (invalidate token if it has)
        $currentQueryHash = md5($keyword . json_encode($filters));
        if ($state['query_hash'] && $state['query_hash'] !== $currentQueryHash) {
            Log::info('Query changed, invalidating continuation token', [
                'old_hash' => $state['query_hash'],
                'new_hash' => $currentQueryHash
            ]);
            $state = ['offsets' => [], 'query_hash' => $currentQueryHash];
        } else {
            $state['query_hash'] = $currentQueryHash;
        }

        $baseFetchSize = config('search.fetch_size', 200);
        $fetchSize = $this->calculateFetchSize($perPage, $baseFetchSize);
        
        // Determine starting offset
        if (isset($state['offsets'][$page - 1])) {
            // Sequential navigation or previously visited page
            $offset = $state['offsets'][$page - 1];
            $navigationType = 'sequential';
        } else {
            // First time visiting this page or jumped
            $offset = ($page - 1) * $fetchSize;
            $navigationType = 'calculated';
        }

        // Check cache
        if (config('search.cache_ttl', 0) > 0) {
            $cachedAds = $this->cacheService->getSearchResults(
                $keyword, 
                $filters, 
                $page, 
                $perPage,
                $offset  // Include offset in cache key
            );
            
            if ($cachedAds !== null) {
                // Rebuild response with fresh metadata
                $nextToken = $this->encodeToken($state);
                
                $response = [
                    'data' => $cachedAds,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $this->estimateTotalPages($keyword, $filters, $fetchSize),
                    'continuation_token' => $nextToken,
                    'has_more' => count($cachedAds) === $perPage,
                ];
                
                return $response;
            }
        }
        
        // Load brand limits from database (with caching)
        $brandLimits = $this->getBrandLimits();
        $globalBrandLimit = config('search.brand_limit', 3);
        
        $results = [];
        $thisPageCounts = [];
        $maxIterations = config('search.max_iterations', 10);
        $iterationCount = 0;
        $startOffset = $offset; // Remember where we started
        
        // Multi-iteration fetch until we have a full page
        while (count($results) < $perPage && $iterationCount < $maxIterations) {
            $batch = $this->fetchFromElasticsearch(
                $keyword, 
                $filters, 
                $offset, 
                $fetchSize
            );
            
            $iterationCount++;
            
            // No more results from Elasticsearch
            if (empty($batch)) {
                break;
            }
            
            // Apply per-page brand capping
            foreach ($batch as $ad) {
                $brandId = $ad['brand_id'];
                
                // Get limit for this specific brand (fall back to global)
                $limitForBrand = $brandLimits[$brandId] ?? $globalBrandLimit;
                
                // Skip if brand already has max ads on this page
                if (($thisPageCounts[$brandId] ?? 0) >= $limitForBrand) {
                    continue;
                }
                
                $results[] = $ad;
                $thisPageCounts[$brandId] = ($thisPageCounts[$brandId] ?? 0) + 1;
                
                // Break if we have enough results
                if (count($results) >= $perPage) {
                	$offset += $fetchSize;
                    break 2;
                }
            }
            
            $offset += $fetchSize;
            
            // If batch was smaller than fetch size, we've reached the end
            if (count($batch) < $fetchSize) {
                break;
            }
        }
        
        // Log if multi-iteration occurred (for monitoring)
        if ($iterationCount > 1) {
            Log::info('Multi-iteration search', [
                'keyword' => $keyword,
                'page' => $page,
                'iterations' => $iterationCount,
                'navigation_type' => $navigationType,
                'start_offset' => $startOffset,
                'final_offset' => $offset,
                'results_count' => count($results)
            ]);
        }
        
        // Update state with this page's ending offset
        $state['offsets'][$page] = $offset;
        
        // Encode continuation token
        $nextToken = $this->encodeToken($state);
        
        $response = [
            'data' => $results,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => $this->estimateTotalPages($keyword, $filters, $fetchSize),
            'continuation_token' => $nextToken,
            'has_more' => count($results) === $perPage,
        ];

        // Cache the ads data 
        if (config('search.cache_ttl', 0) > 0) {
            $this->cacheService->cacheSearchResults(
                $keyword, 
                $filters, 
                $page, 
                $perPage, 
                $results,
                $startOffset
            );
        }

        return $response;
    }
    
    /**
     * Get brand limits from database with caching
     * 
     * @return array Brand ID => limit mapping
     */
    private function getBrandLimits(): array
    {
        return Cache::remember('brand_limits', 3600, function () {
            // Fetch from database
            $brands = \App\Models\Brand::whereNotNull('ad_limit')
                ->pluck('ad_limit', 'id')
                ->toArray();
            
            Log::info('Brand limits loaded from database', [
                'count' => count($brands)
            ]);
            
            return $brands;
        });
    }

   /**
     * Calculate optimal fetch size based on per_page
     * 
     * Larger per_page values need larger fetch sizes to reduce multi-iteration overhead.
     * 
     * @param int $perPage Desired results per page
     * @param int $baseFetchSize Base fetch size from config
     * @return int Calculated fetch size
     */
    private function calculateFetchSize(int $perPage, int $baseFetchSize): int
    {
        // Strategy: Fetch enough to get desired results after brand capping
        // Multiplier approach:
        // - For per_page = 20: multiplier = 10 (fetch 200)
        // - For per_page = 50: multiplier = 10 (fetch 500)
        // - For per_page = 100: multiplier = 10 (fetch 1000)
        
        $multiplier = 10;
        $calculatedSize = $perPage * $multiplier;
        
        // Use whichever is larger: calculated or base
        // This is just in case of concentrated brands results
        $fetchSize = max($calculatedSize, $baseFetchSize);
        
        // Cap at reasonable maximum to avoid memory issues
        $maxFetchSize = 2000;
        $fetchSize = min($fetchSize, $maxFetchSize);
        
        return $fetchSize;
    }
    
    /**
     * Fetch results from Elasticsearch
     * 
     * @param string $keyword Search keyword
     * @param array $filters Additional filters
     * @param int $offset Starting offset
     * @param int $size Number of results to fetch
     * @return array Array of ads
     */
    private function fetchFromElasticsearch(
        string $keyword,
        array $filters,
        int $offset,
        int $size
    ): array {
        // Build must clauses (scored queries)
        $mustClauses = [
            [
                'multi_match' => [
                    'query' => $keyword,
                    'fields' => ['title^2', 'keywords'],  // Title boosted 2x
                    'fuzziness' => 'AUTO',
                    'prefix_length' => 1,
                    'operator' => 'or'
                ]
            ]
        ];
        
        // Build filter clauses (not scored, cacheable)
        $filterClauses = [];
        
        if (!empty($filters['country_iso'])) {
            $filterClauses[] = [
                'term' => ['country_iso' => $filters['country_iso']]
            ];
        }
        
        if (!empty($filters['start_date'])) {
            $filterClauses[] = [
                'range' => [
                    'start_date' => ['gte' => $filters['start_date']]
                ]
            ];
        }
        
        try {
            $response = $this->client->search([
                'index' => $this->indexName,
                'from' => $offset,
                'size' => $size,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => $mustClauses,
                            'filter' => $filterClauses
                        ]
                    ],
                    'sort' => [
                        ['_score' => ['order' => 'desc']],
                        ['relevance_score' => ['order' => 'desc']],
                        ['id' => ['order' => 'asc']]  // Tie-breaker for stable sort
                    ],
                    '_source' => [
                        'id', 'brand_id', 'brand_name', 'title', 
                        'keywords', 'country_iso', 'start_date', 'relevance_score'
                    ]
                ]
            ]);
            
            // Transform hits to simple array
            return array_map(function ($hit) {
                $source = $hit['_source'];
                $source['_score'] = $hit['_score'];
                return $source;
            }, $response['hits']['hits'] ?? []);
            
        } catch (\Exception $e) {
            Log::error('Elasticsearch query failed', [
                'keyword' => $keyword,
                'filters' => $filters,
                'offset' => $offset,
                'size' => $size,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Search query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Estimate total pages based on Elasticsearch count
     * 
     * @param string $keyword Search keyword
     * @param array $filters Filters
     * @param int $fetchSize Fetch size per iteration
     * @return int Estimated total pages
     */
    private function estimateTotalPages(string $keyword, array $filters, int $fetchSize): int
    {
        try {
            $response = $this->client->count([
                'index' => $this->indexName,
                'body' => [
                    'query' => $this->buildQuery($keyword, $filters)
                ]
            ]);
            
            $totalMatches = $response['count'] ?? 0;
            
            // Conservative estimate: Each page might need full fetchSize
            // This is an upper bound
            return (int) ceil($totalMatches / $fetchSize);
            
        } catch (\Exception $e) {
            Log::warning('Failed to estimate total pages', [
                'error' => $e->getMessage()
            ]);
            
            // Return a safe default
            return 1;
        }
    }
    
    /**
     * Build Elasticsearch query (for count and search)
     * 
     * @param string $keyword Search keyword
     * @param array $filters Filters
     * @return array Elasticsearch query
     */
    private function buildQuery(string $keyword, array $filters): array
    {
        $mustClauses = [
            [
                'multi_match' => [
                    'query' => $keyword,
                    'fields' => ['title^2', 'keywords'],
                    'fuzziness' => 'AUTO',
                    'prefix_length' => 1,
                    'operator' => 'or'
                ]
            ]
        ];
        
        $filterClauses = [];
        
        if (!empty($filters['country_iso'])) {
            $filterClauses[] = ['term' => ['country_iso' => $filters['country_iso']]];
        }
        
        if (!empty($filters['start_date'])) {
            $filterClauses[] = [
                'range' => ['start_date' => ['gte' => $filters['start_date']]]
            ];
        }
        
        return [
            'bool' => [
                'must' => $mustClauses,
                'filter' => $filterClauses
            ]
        ];
    }
    
    /**
     * Encode continuation token (opaque to client)
     * 
     * Token structure:
     * {
     *   "offsets": {"1": 600, "2": 1000, ...},
     *   "query_hash": "abc123"
     * }
     * 
     * @param array $state State to encode
     * @return string Base64-encoded, compressed, signed token
     */
    private function encodeToken(array $state): string
    {
        $json = json_encode($state);
        
        // Sign with HMAC to prevent tampering
        $signature = hash_hmac('sha256', $json, config('app.key'));
        
        $payload = json_encode([
            'data' => $state,
            'sig' => $signature,
        ]);
        
        // Compress to reduce size
        $compressed = gzcompress($payload, 6);
        
        // Base64 encode for URL safety
        return rtrim(strtr(base64_encode($compressed), '+/', '-_'), '=');
    }
    
    /**
     * Decode continuation token
     * 
     * @param string $token Encoded token from client
     * @return array Decoded state
     * @throws \Exception If token is invalid or tampered
     */
    private function decodeToken(string $token): array
    {
        try {
            // Decode base64
            // Convert back to standard base64
			$base64 = strtr($token, '-_', '+/');

			// Add padding if needed
			$remainder = strlen($base64) % 4;
			if ($remainder) {
			    $base64 .= str_repeat('=', 4 - $remainder);
			}

			$compressed = base64_decode($base64, true);
            if ($compressed === false) {
                throw new \Exception('Invalid base64 encoding');
            }
            
            // Decompress
            $json = gzuncompress($compressed);
            if ($json === false) {
                throw new \Exception('Failed to decompress token');
            }
            
            // Parse JSON
            $payload = json_decode($json, true);
            if ($payload === null) {
                throw new \Exception('Invalid JSON in token');
            }
            
            // Verify signature
            $expectedSig = hash_hmac('sha256', json_encode($payload['data']), config('app.key'));
            if (!isset($payload['sig']) || !hash_equals($expectedSig, $payload['sig'])) {
                throw new \Exception('Invalid token signature - token may have been tampered with');
            }
            
            return $payload['data'];
            
        } catch (\Exception $e) {
            Log::error('Failed to decode continuation token', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 50) . '...'
            ]);
            
            // Return empty state (fall back to calculation)
            return ['offsets' => [], 'query_hash' => null];
        }
    }
    
    /**
     * Invalidate brand limits cache
     * 
     * Call this when brand limits are updated in the database
     */
    public function invalidateBrandLimitsCache(): void
    {
        Cache::forget('brand_limits');
        Log::info('Brand limits cache invalidated');
    }
}