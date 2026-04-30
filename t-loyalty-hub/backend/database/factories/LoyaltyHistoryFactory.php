<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\LoyaltyHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyHistory>
 */
class LoyaltyHistoryFactory extends Factory
{
    protected $model = LoyaltyHistory::class;

    public function definition(): array
    {
        return [
            'transaction_id' => $this->faker->unique()->numberBetween(10_000, 9_999_999),
            'account_id' => Account::factory(),
            'cashback_amount' => $this->faker->randomFloat(2, 10, 5000),
            'payout_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
