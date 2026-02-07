<?php

return [
    'host' => env('ELASTICSEARCH_HOST', 'elasticsearch:9200'),
    'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
    
    'indices' => [
        'ads' => [
            'name' => env('ELASTICSEARCH_INDEX_ADS', 'ads'),
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'analysis' => [
                    'analyzer' => [
                        'ads_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'stop', 'snowball']
                        ]
                    ]
                ]
            ],
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'brand_id' => ['type' => 'keyword'],
                    'brand_name' => ['type' => 'keyword'],
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'ads_analyzer',
                    ],
                    'keywords' => [
                        'type' => 'text',
                        'analyzer' => 'ads_analyzer'
                    ],
                    'country_iso' => ['type' => 'keyword'],
                    'start_date' => ['type' => 'date'],
                    'relevance_score' => ['type' => 'float'],
                    'created_at' => ['type' => 'date'],
                ]
            ]
        ]
    ]
];