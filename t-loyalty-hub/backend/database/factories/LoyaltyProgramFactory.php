<?php

namespace Database\Factories;

use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyProgram>
 */
class LoyaltyProgramFactory extends Factory
{
    protected $model = LoyaltyProgram::class;

    public function definition(): array
    {
        return [
            'loyalty_program_id' => $this->faker->unique()->numberBetween(1, 9999),
            'loyalty_program_name' => $this->faker->randomElement(['Black', 'All Airlines', 'Bravo', 'Platinum']),
            'cashback_currency' => $this->faker->randomElement(['RUB', 'MILES', 'BRAVO']),
        ];
    }
}
