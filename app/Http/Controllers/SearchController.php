<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search for ads with brand capping and pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
    	//Dirty fix to force JSON response
    	//Otherwise we will have to add the header in the curl
    	$request->headers->set('Accept', 'application/json');
    	
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
            'country_iso' => 'nullable|string|size:2|regex:/^[A-Z]{2}$/',
            'start_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1|max:1000',
            'token' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $keyword = $validated['q'];
        $filters = array_filter([
            'country' => $validated['country_iso'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
        ]);
        $page = $validated['page'] ?? 1;
        $token = $validated['token'] ?? null;
        $perPage = $validated['per_page'] ?? 20;

        try {
            $results = $this->searchService->search(
                $keyword,
                $filters,
                $page,
                $token,
                $perPage
            );

            return response()->json($results);
            
        } catch (\Exception $e) {
            \Log::error('Search request failed', [
                'keyword' => $keyword,
                'filters' => $filters,
                'page' => $page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Search failed',
                'message' => 'An error occurred while searching. Please try again.'
            ], 500);
        }
    }
}