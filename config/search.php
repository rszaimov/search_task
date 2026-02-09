<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Brand Limit Per Page
    |--------------------------------------------------------------------------
    |
    | Global default maximum number of ads per brand per page.
    | Individual brands can override this via the database.
    |
    */
    'brand_limit' => env('SEARCH_BRAND_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Results Per Page
    |--------------------------------------------------------------------------
    |
    | Default number of results to return per page.
    | Users can override this via the per_page parameter (max 100).
    |
    */
    'page_size' => env('SEARCH_PAGE_SIZE', 20),

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Fetch Size
    |--------------------------------------------------------------------------
    |
    | Number of documents to fetch from Elasticsearch per iteration.
    | Larger values reduce iterations but increase network transfer.
    |
    | Recommendation: 200-500 depending on brand distribution.
    |
    */
    'fetch_size' => env('SEARCH_FETCH_SIZE', 200),

    /*
    |--------------------------------------------------------------------------
    | Maximum Iterations
    |--------------------------------------------------------------------------
    |
    | Safety valve: Maximum number of fetch iterations per page.
    | Prevents infinite loops with highly skewed brand distributions.
    |
    */
    'max_iterations' => env('SEARCH_MAX_ITERATIONS', 10),

    /*
    |--------------------------------------------------------------------------
    | Maximum Page Number
    |--------------------------------------------------------------------------
    |
    | Maximum page number users can request.
    | Prevents abuse via deep pagination.
    |
    */
    'max_page' => env('SEARCH_MAX_PAGE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Time-to-live for cached search results (in seconds).
    | Set to 0 to disable caching.
    |
    */
    'cache_ttl' => env('SEARCH_CACHE_TTL', 300),
];