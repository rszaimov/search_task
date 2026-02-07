<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ad>
 */
class AdFactory extends Factory
{
    protected $model = Ad::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $keywords = $this->faker->randomElements(
            ['sneakers', 'running', 'sports', 'fitness', 'shoes', 'outdoor', 'apparel'],
            rand(2, 4)
        );

        return [
            'brand_id' => Brand::factory(),
            'keywords' => implode(', ', $keywords),
            'country_iso' => $this->getCountry(),
            'start_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'relevance_score' => $this->faker->randomFloat(4, 0, 1),
        ];
    }

    public function configure()
    {
        //use this to include the brand name in the ad title
        return $this->afterMaking(function ($ad) {
            $ad->title = $ad->brand->name . ' ' . ucfirst(
                $this->faker->words(rand(2, 4), true)
            );
        });
    }

    private function getCountry(): string
    {
        //country distribution with ~60% US, ~20% CB and ~10% for AU and CA
        $rand = $this->faker->randomFloat(2, 0, 1);

        if ($rand < 0.60) {
            $country = 'US';
        } elseif ($rand < 0.80) {
            $country = 'GB';
        } elseif ($rand < 0.90) {
            $country = 'AU';
        } else {
            $country = 'CA';
        }

        return $country;
    }
}
