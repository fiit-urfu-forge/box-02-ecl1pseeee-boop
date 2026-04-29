<?php

declare(strict_types=1);

/*
 * Centralised business rules from §4.1 of the SPEC.
 * Always read these via config('digitalbank.*') — never via env() at runtime,
 * so `php artisan config:cache` keeps working in production.
 */

return [
    'transfer' => [
        'min_amount' => env('MIN_TRANSFER_AMOUNT', '1.00'),
        'max_amount' => env('MAX_TRANSFER_AMOUNT', '100000.00'),
        'daily_limit' => env('DAILY_TRANSFER_LIMIT', '300000.00'),
    ],

    'accounts' => [
        'max_per_user' => (int) env('MAX_ACCOUNTS_PER_USER', 5),
        'currency_prefix' => [
            // First 3 digits of the 20-digit account number (§5.2).
            'RUB' => '810',
            'USD' => '840',
        ],
    ],

    'idempotency' => [
        'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
    ],

    'avatar' => [
        'max_size_kb' => (int) env('AVATAR_MAX_SIZE_KB', 5120),
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'sbp' => [
        'gateway' => env('SBP_GATEWAY', 'mock'),
        'webhook_secret' => env('SBP_WEBHOOK_SECRET'),
    ],
];
