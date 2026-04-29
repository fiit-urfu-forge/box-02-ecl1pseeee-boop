<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// §4.1 / §8.3 — cron cleanup of expired idempotency keys (TTL 24h).
// Runs hourly; can be invoked manually via `php artisan digitalbank:idempotency:cleanup`.
Schedule::command('digitalbank:idempotency:cleanup')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
