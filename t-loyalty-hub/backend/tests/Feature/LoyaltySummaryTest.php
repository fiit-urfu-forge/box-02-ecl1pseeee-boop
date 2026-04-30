<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_cashback_per_currency(): void
    {
        $user = User::factory()->segment('HIGH')->create();
        $rub = LoyaltyProgram::factory()->create(['cashback_currency' => 'RUB']);
        $miles = LoyaltyProgram::factory()->create(['cashback_currency' => 'MILES']);

        $rubAccount = Account::factory()->create([
            'user_id' => $user->id,
            'loyalty_program_id' => $rub->loyalty_program_id,
            'current_balance' => 1500.00,
        ]);
        $milesAccount = Account::factory()->create([
            'user_id' => $user->id,
            'loyalty_program_id' => $miles->loyalty_program_id,
            'current_balance' => 200.00,
        ]);

        LoyaltyHistory::factory()->create(['account_id' => $rubAccount->account_id, 'cashback_amount' => 100]);
        LoyaltyHistory::factory()->create(['account_id' => $rubAccount->account_id, 'cashback_amount' => 250]);
        LoyaltyHistory::factory()->create(['account_id' => $milesAccount->account_id, 'cashback_amount' => 50]);

        $response = $this->getJson("/api/loyalty/summary?user_id={$user->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['by_currency', 'totals' => ['rub', 'miles', 'bravo', 'total_transactions', 'total_balance'], 'programs']]);

        $totals = $response->json('data.totals');
        $this->assertEquals(350.00, $totals['rub']);
        $this->assertEquals(50.00, $totals['miles']);
        $this->assertEquals(0, $totals['bravo']);
        $this->assertEquals(3, $totals['total_transactions']);
        $this->assertEquals(1700.00, $totals['total_balance']);
    }

    public function test_summary_returns_404_for_unknown_user(): void
    {
        $this->getJson('/api/loyalty/summary?user_id=99999')->assertStatus(422);
    }

    public function test_history_paginates_in_descending_date_order(): void
    {
        $user = User::factory()->create();
        $program = LoyaltyProgram::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'loyalty_program_id' => $program->loyalty_program_id,
        ]);

        LoyaltyHistory::factory()->create(['account_id' => $account->account_id, 'cashback_amount' => 100, 'payout_date' => '2025-01-01']);
        LoyaltyHistory::factory()->create(['account_id' => $account->account_id, 'cashback_amount' => 200, 'payout_date' => '2025-06-15']);
        LoyaltyHistory::factory()->create(['account_id' => $account->account_id, 'cashback_amount' => 300, 'payout_date' => '2025-12-31']);

        $response = $this->getJson("/api/loyalty/history?user_id={$user->id}&per_page=10")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $dates = collect($response->json('data'))->pluck('payout_date')->all();
        $this->assertEquals(['2025-12-31', '2025-06-15', '2025-01-01'], $dates);
    }
}
