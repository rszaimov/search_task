<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SearchService
{
    private $client;
    private string $indexName;

    public function __construct()
    {
        $this->client = app('elasticsearch');
        $this->indexName = config('elasticsearch.indices.ads.name');
    }

    /**
     * Search for ads with filters.
     *
     * @param string $keyword Search term (typo-tolerant)
     * @param array $filters ['country_iso' => 'US', 'start_date' => '2026-01-01']
     * @param int $page Page number (1-indexed)
     * @param int $perPage Results per page
     * @return array ['data' => [...], 'total' => 100, 'page' => 1, 'per_page' => 20]
     */
    public function search(
        string $keyword,
        array $filters = [],
        int $page = 1,
        int $perPage = 20
    ): array {
        $from = ($page - 1) * $perPage;

        $query = $this->buildQuery($keyword, $filters);

        $response = $this->client->search([
            'index' => $this->indexName,
            'from' => $from,
            'size' => $perPage,
            'body' => $query
        ]);

        //Transform results
        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? 0;

        $data = array_map(function ($hit) {
            return [
                'id' => $hit['_source']['id'],
                'brand_id' => $hit['_source']['brand_id'],
                'brand_name' => $hit['_source']['brand_name'],
                'title' => $hit['_source']['title'],
                'keywords' => $hit['_source']['keywords'],
                'country_iso' => $hit['_source']['country_iso'],
                'start_date' => $hit['_source']['start_date'],
                'relevance_score' => $hit['_source']['relevance_score'],
            ];
        }, $hits);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Build Elasticsearch query with filters.
     */
    private function buildQuery(string $keyword, array $filters): array
    {
        $mustClauses = [];
        $filterClauses = [];

        //Keyword search with fuzzy matching
        if (!empty($keyword)) {
            $mustClauses[] = [
                'multi_match' => [
                    'query' => $keyword,
                    'fields' => ['title^2', 'keywords'], //Boost title matches
                    'fuzziness' => 'AUTO', //Typo tolerance
                    'prefix_length' => 1,  //First character must match
                ]
            ];
        }

        //Country filter
        if (!empty($filters['country_iso'])) {
            $filterClauses[] = [
                'term' => [
                    'country_iso' => $filters['country_iso']
                ]
            ];
        }

        //Start date filter (ads starting on or after this date)
        if (!empty($filters['start_date'])) {
            $filterClauses[] = [
                'range' => [
                    'start_date' => [
                        'gte' => $filters['start_date']
                    ]
                ]
            ];
        }

        return [
            'query' => [
                'bool' => [
                    'must' => $mustClauses,
                    'filter' => $filterClauses
                ]
            ],
            'sort' => [
                ['_score' => ['order' => 'desc']], //Relevance first
                ['relevance_score' => ['order' => 'desc']], //Then custom score
                ['id' => ['order' => 'asc']] //Stable sort
            ]
        ];
    }
}