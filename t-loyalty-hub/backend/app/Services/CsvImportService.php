<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyProgram;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class CsvImportService
{
    private const CHUNK_SIZE = 500;

    private const CURRENCY_MAP = [
        'rub' => 'RUB',
        'miles' => 'MILES',
        'bravo-points' => 'BRAVO',
        'bravo' => 'BRAVO',
    ];

    public function importAll(string $dataDir): array
    {
        $stats = [];

        DB::transaction(function () use ($dataDir, &$stats) {
            $stats['loyalty_programs'] = $this->importLoyaltyPrograms("{$dataDir}/LoyaltyPrograms.csv");
            $stats['users'] = $this->importUsers("{$dataDir}/Users.csv");
            $stats['accounts'] = $this->importAccounts("{$dataDir}/Accounts.csv");
            $stats['offers'] = $this->importOffers("{$dataDir}/Offers.csv");
            $stats['loyalty_history'] = $this->importLoyaltyHistory("{$dataDir}/LoyaltyHistory.csv");
        });

        $this->flushCaches();

        return $stats;
    }

    private function importLoyaltyPrograms(string $path): int
    {
        return $this->importCsv($path, function (array $rows) {
            $rows = array_map(fn ($r) => [
                'loyalty_program_id' => (int) $r['loyalty_program_id'],
                'loyalty_program_name' => $r['loyalty_program_name'],
                'cashback_currency' => $this->normalizeCurrency($r['cashback_currency']),
            ], $rows);

            LoyaltyProgram::upsert(
                $rows,
                uniqueBy: ['loyalty_program_id'],
                update: ['loyalty_program_name', 'cashback_currency']
            );
        });
    }

    private function importUsers(string $path): int
    {
        return $this->importCsv($path, function (array $rows) {
            $rows = array_map(fn ($r) => [
                'id' => (int) $r['id'],
                'email' => $r['email'],
                'phone_number' => $r['phone_number'] ?? null,
                'full_name' => $r['full_name'],
                'financial_segment' => strtoupper($r['financial_segment']),
            ], $rows);

            User::upsert(
                $rows,
                uniqueBy: ['id'],
                update: ['email', 'phone_number', 'full_name', 'financial_segment']
            );
        });
    }

    private function importAccounts(string $path): int
    {
        return $this->importCsv($path, function (array $rows) {
            $rows = array_map(fn ($r) => [
                'account_id' => (int) $r['account_id'],
                'user_id' => (int) $r['user_id'],
                'loyalty_program_id' => (int) $r['loyalty_program_id'],
                'current_balance' => (float) $r['current_balance'],
            ], $rows);

            Account::upsert(
                $rows,
                uniqueBy: ['account_id'],
                update: ['user_id', 'loyalty_program_id', 'current_balance']
            );
        });
    }

    private function importOffers(string $path): int
    {
        return $this->importCsv($path, function (array $rows) {
            $rows = array_map(fn ($r) => [
                'partner_id' => (int) $r['partner_id'],
                'partner_name' => $r['partner_name'],
                'short_description' => $r['short_description'] ?? null,
                'logo_url' => $r['logo_url'] ?? null,
                'brand_color_hex' => $r['brand_color_hex'] ?? null,
                'cashback_percent' => (float) $r['cashback_percent'],
                'financial_segment' => strtoupper($r['financial_segment']),
            ], $rows);

            Offer::upsert(
                $rows,
                uniqueBy: ['partner_id', 'financial_segment'],
                update: ['partner_name', 'short_description', 'logo_url', 'brand_color_hex', 'cashback_percent']
            );
        });
    }

    private function importLoyaltyHistory(string $path): int
    {
        return $this->importCsv($path, function (array $rows) {
            $rows = array_map(fn ($r) => [
                'transaction_id' => (int) $r['transaction_id'],
                'account_id' => (int) $r['account_id'],
                'cashback_amount' => (float) $r['cashback_amount'],
                'payout_date' => $r['payout_date'],
            ], $rows);

            LoyaltyHistory::upsert(
                $rows,
                uniqueBy: ['transaction_id'],
                update: ['account_id', 'cashback_amount', 'payout_date']
            );
        });
    }

    private function importCsv(string $path, callable $handler): int
    {
        if (!is_file($path)) {
            throw new \RuntimeException("CSV not found: {$path}");
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $chunk = [];
        $count = 0;
        foreach ($csv->getRecords() as $record) {
            $chunk[] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $record);
            $count++;

            if (count($chunk) >= self::CHUNK_SIZE) {
                $handler($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $handler($chunk);
        }

        return $count;
    }

    private function normalizeCurrency(string $value): string
    {
        $key = strtolower(trim($value));
        return self::CURRENCY_MAP[$key] ?? strtoupper($value);
    }

    private function flushCaches(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable) {
            // Cache driver not available — skip silently
        }
    }
}
