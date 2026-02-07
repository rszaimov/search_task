<?php

namespace App\Console\Commands;

use App\Models\Ad;
use Illuminate\Console\Command;

class ElasticsearchReindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:reindex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex all ads to Elasticsearch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = app('elasticsearch');
        $indexName = config('elasticsearch.indices.ads.name');
        
        $this->info('Starting reindex...');
        $bar = $this->output->createProgressBar(Ad::count());
        
        Ad::with('brand')->chunk(500, function ($ads) use ($client, $indexName, $bar) {
            $params = ['body' => []];
            
            foreach ($ads as $ad) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $indexName,
                        '_id' => $ad->id
                    ]
                ];
                
                $params['body'][] = [
                    'id' => $ad->id,
                    'brand_id' => $ad->brand_id,
                    'brand_name' => $ad->brand->name,
                    'title' => $ad->title,
                    'keywords' => $ad->keywords,
                    'country_iso' => $ad->country_iso,
                    'start_date' => $ad->start_date->format('Y-m-d'),
                    'relevance_score' => (float)$ad->relevance_score,
                    'created_at' => $ad->created_at->toIso8601String(),
                ];
                
                $bar->advance();
            }
            
            if (!empty($params['body'])) {
                $client->bulk($params);
            }
        });
        
        $bar->finish();
        $this->newLine();
        $this->info('✓ Reindexing complete!');
        
        return Command::SUCCESS;
    }
}
