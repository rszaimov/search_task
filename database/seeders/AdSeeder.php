<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = Brand::all();

        //Nike is the dominant brand with 3000 ads
        $brandNike = $brands->first();
        Ad::factory()->count(3000)->for($brandNike)->create();

        //Adidas is the secondary with 1000 ads
        $brandAdidas = $brands->skip(1)->first();
        Ad::factory()->count(1000)->for($brandAdidas)->create();

        //Remaining 1000 ads distributed across the rest
        $remainingAds = 1000;
        $otherBrands = $brands->skip(2)->values();

        for ($i = 0; $i < $remainingAds; $i++) {
		    $brand = $otherBrands->random();

		    Ad::factory()
		        ->for($brand)
		        ->create();
		}

    }
}
