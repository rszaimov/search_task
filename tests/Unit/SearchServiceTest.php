<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private SearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchService = new SearchService();
    }

    public function test_search_with_keyword_returns_results()
    {
        $result = $this->searchService->search('sneakers');

        $this->assertGreaterThan(0, $result['total_pages']);
        $this->assertNotEmpty($result['data']);
    }

    public function test_search_with_country_filter()
    {
        $result = $this->searchService->search('shoes', ['country_iso' => 'US']);

        foreach ($result['data'] as $ad) {
            $this->assertEquals('US', $ad['country_iso']);
        }
    }

    public function test_search_with_start_date_filter()
    {
        $result = $this->searchService->search('running', [
            'start_date' => '2026-01-10'
        ]);

        foreach ($result['data'] as $ad) {
            $this->assertGreaterThanOrEqual(
                '2026-01-10',
                $ad['start_date']
            );
        }
    }

    public function test_search_with_multiple_filters()
    {
        $result = $this->searchService->search('fitness', [
            'country_iso' => 'US',
            'start_date' => '2026-01-01'
        ]);

        foreach ($result['data'] as $ad) {
            $this->assertEquals('US', $ad['country_iso']);
            $this->assertGreaterThanOrEqual('2026-01-01', $ad['start_date']);
        }
    }

    public function test_fuzzy_search_handles_typos()
    {
        //Search with typo
        $typoResults = $this->searchService->search('snakers'); // Missing 'e'
        
        //Should still find "sneakers"
        $this->assertGreaterThan(0, count($typoResults['data']));
    }

    public function test_no_results_returns_empty_array()
    {
        // Search for something that definitely doesn't exist
        $result = $this->searchService->search('fgfdgdfgsdgjsdgjdggdf');

        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['total_pages']);
        $this->assertFalse($result['has_more']);
    }

    public function test_search_returns_correct_page_number()
    {
        $result = $this->searchService->search('sneakers', [], 3, null, 20);

        $this->assertEquals(3, $result['current_page']);
    }

    public function test_different_page_sizes()
    {
        $result10 = $this->searchService->search('sneakers', [], 1, null, 10);
        $result50 = $this->searchService->search('sneakers', [], 1, null, 50);

        $this->assertLessThanOrEqual(10, count($result10['data']));
        $this->assertLessThanOrEqual(50, count($result50['data']));
        $this->assertEquals(10, $result10['per_page']);
        $this->assertEquals(50, $result50['per_page']);
    }

    public function test_applies_per_page_brand_cap()
    {
        // This test would work best with a separate test DB and seeded test data
        $brandLimits = \App\Models\Brand::whereNotNull('ad_limit')
                ->pluck('ad_limit', 'id')
                ->toArray();
        $globalBrandLimit = config('search.brand_limit', 3);
        
        $result1 = $this->searchService->search('shoes', [], 1, null, 20);
        $result2 = $this->searchService->search('shoes', [], 2, null, 20);

        // Count ads per brand
        $brandCounts1 = [];
        foreach ($result1['data'] as $ad) {
            $brandId = $ad['brand_id'];
            $brandCounts1[$brandId] = ($brandCounts1[$brandId] ?? 0) + 1;
        }
        $brandCounts2 = [];
        foreach ($result2['data'] as $ad) {
            $brandId = $ad['brand_id'];
            $brandCounts2[$brandId] = ($brandCounts2[$brandId] ?? 0) + 1;
        }

        foreach ($brandCounts1 as $brandId => $adsCount) {
            $limitForBrand = $brandLimits[$brandId] ?? $globalBrandLimit;
            $this->assertLessThanOrEqual($limitForBrand, $adsCount);
        }

        foreach ($brandCounts2 as $brandId => $adsCount) {
            $limitForBrand = $brandLimits[$brandId] ?? $globalBrandLimit;
            $this->assertLessThanOrEqual($limitForBrand, $adsCount);
        }
    }

}