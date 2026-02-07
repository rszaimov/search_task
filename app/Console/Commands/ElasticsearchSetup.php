<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ElasticsearchSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Elasticsearch indices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = app('elasticsearch');
        $indexConfig = config('elasticsearch.indices.ads');
        
        try {
            // Delete index if exists
            if ($client->indices()->exists(['index' => $indexConfig['name']])->asBool()) {
                $client->indices()->delete(['index' => $indexConfig['name']]);
                $this->info('Deleted existing index');
            }
            
            // Create index
            $client->indices()->create([
                'index' => $indexConfig['name'],
                'body' => [
                    'settings' => $indexConfig['settings'],
                    'mappings' => $indexConfig['mappings']
                ]
            ]);
            
            $this->info('✓ Elasticsearch index created successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create index: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
