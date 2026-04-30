<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyProgram;
use App\Models\Offer;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_full_csv_dataset(): void
    {
        $dir = realpath(__DIR__ . '/../../storage/app/data');
        $this->assertNotFalse($dir, 'CSV fixtures must be staged in storage/app/data');

        $stats = app(CsvImportService::class)->importAll($dir);

        $this->assertGreaterThan(0, $stats['users']);
        $this->assertGreaterThan(0, $stats['loyalty_programs']);
        $this->assertGreaterThan(0, $stats['accounts']);
        $this->assertGreaterThan(0, $stats['offers']);
        $this->assertGreaterThan(0, $stats['loyalty_history']);

        $this->assertSame($stats['users'], User::count());
        $this->assertSame($stats['loyalty_programs'], LoyaltyProgram::count());
        $this->assertSame($stats['accounts'], Account::count());
        $this->assertSame($stats['offers'], Offer::count());
        $this->assertSame($stats['loyalty_history'], LoyaltyHistory::count());

        $currencies = LoyaltyProgram::pluck('cashback_currency')->unique()->sort()->values()->all();
        $this->assertEquals(['BRAVO', 'MILES', 'RUB'], $currencies);
    }
}
