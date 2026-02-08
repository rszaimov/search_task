<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Search for ads.
     *
     * GET /api/search?q=sneakers&country_iso=US&start_date=2026-01-01&page=1&per_page=20
     */
    public function search(Request $request): JsonResponse
    {
    	//Dirty fix to force JSON response
    	//Otherwise we will have to add the header in the curl
    	$request->headers->set('Accept', 'application/json');

        //Input validation
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
            'country_iso' => 'nullable|string|size:2|uppercase',
            'start_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1|max:1000',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $keyword = $validated['q'];

        $filters = array_filter([
            'country_iso' => $validated['country_iso'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
        ]);
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        try {
            //Perform search
            $results = $this->searchService->search(
                $keyword,
                $filters,
                $page,
                $perPage
            );

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}