<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupIdempotencyKeys extends Command
{
    protected $signature = 'digitalbank:idempotency:cleanup';

    protected $description = 'Delete idempotency_keys rows whose TTL has expired (§4.1 of SPEC).';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted $deleted expired idempotency keys.");

        Log::channel('daily')->info('idempotency.cleanup', [
            'deleted' => $deleted,
        ]);

        return self::SUCCESS;
    }
}
