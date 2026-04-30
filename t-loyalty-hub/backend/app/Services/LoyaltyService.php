<?php

namespace App\Services;

use App\Models\LoyaltyHistory;
use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    private const CACHE_TTL = 300;

    /**
     * @return array{by_currency: array<int, array{currency: string, total: float, transactions: int}>, totals: array{rub: float, miles: float, bravo: float, total_transactions: int, total_balance: float}, programs: array<int, array<string, mixed>>}
     */
    public function getSummary(User $user): array
    {
        return Cache::remember(
            "loyalty:summary:{$user->id}",
            self::CACHE_TTL,
            fn () => $this->buildSummary($user)
        );
    }

    public function getHistory(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return LoyaltyHistory::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id))
            ->with(['account.loyaltyProgram'])
            ->orderByDesc('payout_date')
            ->orderByDesc('transaction_id')
            ->paginate($perPage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPrograms(User $user): array
    {
        $rows = DB::table('accounts as a')
            ->join('loyalty_programs as lp', 'lp.loyalty_program_id', '=', 'a.loyalty_program_id')
            ->where('a.user_id', $user->id)
            ->select('lp.loyalty_program_id', 'lp.loyalty_program_name', 'lp.cashback_currency', 'a.account_id', 'a.current_balance')
            ->orderBy('lp.loyalty_program_id')
            ->get();

        return $rows->map(fn ($r) => [
            'loyalty_program_id' => (int) $r->loyalty_program_id,
            'loyalty_program_name' => $r->loyalty_program_name,
            'cashback_currency' => $r->cashback_currency,
            'account_id' => (int) $r->account_id,
            'current_balance' => (float) $r->current_balance,
        ])->all();
    }

    private function buildSummary(User $user): array
    {
        $byCurrency = DB::table('accounts as a')
            ->join('loyalty_programs as lp', 'lp.loyalty_program_id', '=', 'a.loyalty_program_id')
            ->leftJoin('loyalty_history as lh', 'lh.account_id', '=', 'a.account_id')
            ->where('a.user_id', $user->id)
            ->groupBy('lp.cashback_currency')
            ->selectRaw('lp.cashback_currency as currency, COALESCE(SUM(lh.cashback_amount), 0) as total, COUNT(lh.transaction_id) as transactions')
            ->get();

        $totals = [
            'rub' => 0.0,
            'miles' => 0.0,
            'bravo' => 0.0,
            'total_transactions' => 0,
            'total_balance' => 0.0,
        ];

        $byCurrencyArray = [];
        foreach ($byCurrency as $row) {
            $key = strtolower($row->currency);
            $total = (float) $row->total;
            $tx = (int) $row->transactions;

            if (array_key_exists($key, $totals)) {
                $totals[$key] += $total;
            }
            $totals['total_transactions'] += $tx;

            $byCurrencyArray[] = [
                'currency' => $row->currency,
                'total' => $total,
                'transactions' => $tx,
            ];
        }

        $totals['total_balance'] = (float) DB::table('accounts')
            ->where('user_id', $user->id)
            ->sum('current_balance');

        return [
            'by_currency' => $byCurrencyArray,
            'totals' => $totals,
            'programs' => $this->getPrograms($user),
        ];
    }
}
