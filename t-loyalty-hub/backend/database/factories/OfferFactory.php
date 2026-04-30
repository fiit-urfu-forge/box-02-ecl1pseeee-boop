<?php

namespace Database\Factories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'partner_id' => $this->faker->unique()->numberBetween(1, 99_999),
            'partner_name' => $this->faker->company(),
            'short_description' => $this->faker->sentence(4),
            'logo_url' => $this->faker->imageUrl(64, 64, 'business'),
            'brand_color_hex' => $this->faker->hexColor(),
            'cashback_percent' => $this->faker->randomFloat(2, 1, 20),
            'financial_segment' => $this->faker->randomElement(['ALL', 'LOW', 'MEDIUM', 'HIGH']),
        ];
    }
}
