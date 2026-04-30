<?php

namespace App\Console\Commands;

use App\Services\CsvImportService;
use Illuminate\Console\Command;

class ImportCsvCommand extends Command
{
    protected $signature = 'loyalty:import {--dir= : Directory containing CSV files}';

    protected $description = 'Импорт CSV-датасетов в БД (idempotent upsert)';

    public function handle(CsvImportService $service): int
    {
        $dir = $this->option('dir') ?: config('services.csv.data_dir', storage_path('app/data'));

        if (!is_dir($dir)) {
            $this->error("CSV directory not found: {$dir}");
            return self::FAILURE;
        }

        $this->info("Importing CSVs from: {$dir}");

        try {
            $stats = $service->importAll($dir);
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        foreach ($stats as $entity => $count) {
            $this->line("  • {$entity}: {$count} rows");
        }

        $this->info('Import complete.');
        return self::SUCCESS;
    }
}
