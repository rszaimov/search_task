<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    	//Create Nike and Adidas brands as the other brands will have dummy names
        Brand::factory()->create([
            'name' => 'Nike',
            'ad_limit' => 3,
        ]);

        Brand::factory()->create([
            'name' => 'Adidas',
            'ad_limit' => 3,
        ]);

        //Another 38 brands with ad_limit 3 for a total of 40
        Brand::factory()
            ->count(38)
            ->create(['ad_limit' => 3]);

        //The other 10 will have ad_limit 2
        Brand::factory()
            ->count(10)
            ->create(['ad_limit' => 2]);
    }
}
