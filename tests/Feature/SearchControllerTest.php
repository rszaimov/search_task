<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test search endpoint returns successful response
     */
    public function test_search_endpoint_returns_success()
    {
        $response = $this->getJson('/api/search?q=sneakers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'brand_id',
                    'brand_name',
                    'title',
                    'country_iso',
                    'start_date',
                    'relevance_score',
                    '_score'
                ]
            ],
            'current_page',
            'per_page',
            'total_pages',
            'continuation_token',
            'has_more'
        ]);
    }

    /**
     * Test search requires keyword parameter
     */
    public function test_search_requires_keyword()
    {
        $response = $this->getJson('/api/search');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
        $response->assertJson([
            'message' => 'The q field is required.'
        ]);
    }

    /**
     * Test search keyword must be at least 1 character
     */
    public function test_search_keyword_minimum_length()
    {
        $response = $this->getJson('/api/search?q=');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    /**
     * Test search keyword cannot exceed 100 characters
     */
    public function test_search_keyword_maximum_length()
    {
        $longKeyword = str_repeat('a', 101);
        $response = $this->getJson("/api/search?q={$longKeyword}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    /**
     * Test country code must be 2 characters
     */
    public function test_country_code_must_be_two_characters()
    {
        $response = $this->getJson('/api/search?q=sneakers&country_iso=USA');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['country_iso']);
        $response->assertJson([
            'errors' => [
                'country_iso' => ['The country iso field must be 2 characters.']
            ]
        ]);
    }

    /**
     * Test country code must be uppercase
     */
    public function test_country_code_must_be_uppercase()
    {
        $response = $this->getJson('/api/search?q=sneakers&country_iso=us');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['country_iso']);
    }

    /**
     * Test valid country code accepted
     */
    public function test_valid_country_code_accepted()
    {
        $response = $this->getJson('/api/search?q=sneakers&country_iso=US');

        $response->assertStatus(200);
    }

    /**
     * Test start_date must be in correct format
     */
    public function test_start_date_format_validation()
    {
        $response = $this->getJson('/api/search?q=sneakers&start_date=01-01-2026');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
        $response->assertJson([
            'errors' => [
                'start_date' => ['The start date field must match the format Y-m-d.']
            ]
        ]);
    }

    /**
     * Test page number must be at least 1
     */
    public function test_page_number_minimum()
    {
        $response = $this->getJson('/api/search?q=sneakers&page=0');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page']);
    }

    /**
     * Test page number cannot exceed maximum
     */
    public function test_page_number_maximum()
    {
        $response = $this->getJson('/api/search?q=sneakers&page=1001');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page']);
    }

    /**
     * Test valid page number accepted
     */
    public function test_valid_page_number_accepted()
    {
        $response = $this->getJson('/api/search?q=sneakers&page=5');

        $response->assertStatus(200);
        $response->assertJson(['current_page' => 5]);
    }

    /**
     * Test per_page must be at least 1
     */
    public function test_per_page_minimum()
    {
        $response = $this->getJson('/api/search?q=sneakers&per_page=0');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Test per_page cannot exceed 100
     */
    public function test_per_page_maximum()
    {
        $response = $this->getJson('/api/search?q=sneakers&per_page=101');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Test valid per_page accepted
     */
    public function test_valid_per_page_accepted()
    {
        $response = $this->getJson('/api/search?q=sneakers&per_page=50');

        $response->assertStatus(200);
        $response->assertJson(['per_page' => 50]);
    }

    /**
     * Test search with all valid parameters
     */
    public function test_search_with_all_parameters()
    {
        $futureDate = now()->subDays(60)->format('Y-m-d');
        
        $response = $this->getJson(
            '/api/search?q=sneakers&country_iso=US&start_date=' . $futureDate . '&page=2&per_page=15'
        );

        $response->assertStatus(200);
        $response->assertJson([
            'current_page' => 2,
            'per_page' => 15
        ]);
    }

    /**
     * Test continuation token can be passed
     */
    public function test_continuation_token_accepted()
    {
        // Get page 1 first
        $page1Response = $this->getJson('/api/search?q=sneakers&page=1');
        $token = $page1Response->json('continuation_token');

        if ($token) {
            // Use token for page 2
            $response = $this->getJson("/api/search?q=sneakers&page=2&token={$token}");

            $response->assertStatus(200);
            $response->assertJson(['current_page' => 2]);
        } else {
            $this->markTestSkipped('No continuation token available');
        }
    }

    /**
     * Test invalid continuation token handled gracefully
     */
    public function test_invalid_continuation_token_handled()
    {
        $response = $this->getJson('/api/search?q=sneakers&page=2&token=invalid_token_xyz');

        // Should still work, just falls back to calculated offset
        $response->assertStatus(200);
    }

    /**
     * Test search with country filter returns only that country
     */
    public function test_country_filter_applied()
    {
        $response = $this->getJson('/api/search?q=sneakers&country_iso=US');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $ad) {
            $this->assertEquals('US', $ad['country_iso']);
        }
    }

    /**
     * Test search returns no results for non-existent keyword
     */
    public function test_no_results_for_nonexistent_keyword()
    {
        $response = $this->getJson('/api/search?q=xyznonexistent123456');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
            'has_more' => false
        ]);
    }

    /**
     * Test pagination - page 1 and page 2 have different results
     */
    public function test_pagination_returns_different_results()
    {
        $page1 = $this->getJson('/api/search?q=shoes&page=1&per_page=20');
        $token = $page1->json('continuation_token');

        if (!$token) {
            $this->markTestSkipped('Not enough results for pagination test');
        }

        $page2 = $this->getJson("/api/search?q=shoes&page=2&per_page=20&token={$token}");

        $page1->assertStatus(200);
        $page2->assertStatus(200);

        $page1Ids = array_column($page1->json('data'), 'id');
        $page2Ids = array_column($page2->json('data'), 'id');

        // Pages should have different results (no duplicates)
        $overlap = array_intersect($page1Ids, $page2Ids);
        $this->assertEmpty($overlap, 'Page 1 and Page 2 should not have overlapping results');
    }


    /**
     * Test response time is acceptable
     */
    public function test_response_time_acceptable()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/search?q=sneakers');
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        
        // Should respond in under 2 seconds (2000ms) as per requirements
        $this->assertLessThan(2000, $duration, 
            "Search took {$duration}ms, should be under 2000ms"
        );
    }

    /**
     * Test deep pagination (page 5)
     */
    public function test_deep_pagination()
    {
        // Should be a test for page 50, but we don't have enough data in the PoC
        $response = $this->getJson('/api/search?q=shoes&page=5');

        $response->assertStatus(200);
        $response->assertJson(['current_page' => 5]);
    }

}