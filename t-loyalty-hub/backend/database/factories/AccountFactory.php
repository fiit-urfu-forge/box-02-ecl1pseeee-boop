<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'account_id' => $this->faker->unique()->numberBetween(10_000, 999_999),
            'user_id' => User::factory(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'current_balance' => $this->faker->randomFloat(2, 100, 100_000),
        ];
    }
}
