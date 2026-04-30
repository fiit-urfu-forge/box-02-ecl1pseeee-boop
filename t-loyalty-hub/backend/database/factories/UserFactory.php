<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(10_000, 999_999),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'full_name' => $this->faker->name(),
            'financial_segment' => $this->faker->randomElement(['LOW', 'MEDIUM', 'HIGH']),
        ];
    }

    public function segment(string $segment): static
    {
        return $this->state(fn () => ['financial_segment' => $segment]);
    }
}
