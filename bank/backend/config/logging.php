<?php

use App\Logging\JsonFormatterFactory;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

/*
 * Structured JSON logging per §10.1 of SPEC. Each business-sensitive flow
 * gets its own channel so ops can tail/filter without false positives:
 *   - transfers  — core banking events (success, failed, race)
 *   - auth       — login, logout, register, password events
 *   - accounts   — account status changes
 *   - security   — bad signatures, idempotency misuse, rate-limit blows
 *   - app        — everything else, HTTP 500s, unexpected throws
 *
 * `stack` fans out to all of: daily file for long-term review + stderr for
 * Docker/ELK. Every channel is tapped with JsonFormatterFactory so the
 * output is a single-line JSON record.
 */

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'stderr_json'],
            'ignore_exceptions' => false,
        ],

        // ---------------- default "anything goes" channel ------------------
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/app.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => env('LOG_DAILY_DAYS', 30),
            'replace_placeholders' => true,
            'tap' => [JsonFormatterFactory::class],
        ],

        'stderr_json' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
            'tap' => [JsonFormatterFactory::class],
        ],

        // ---------------- business-sensitive channels ----------------------
        'transfers' => [
            'driver' => 'daily',
            'path' => storage_path('logs/transfers.log'),
            'level' => 'info',
            'days' => 90,
            'tap' => [JsonFormatterFactory::class],
        ],

        'auth' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auth.log'),
            'level' => 'info',
            'days' => 90,
            'tap' => [JsonFormatterFactory::class],
        ],

        'accounts' => [
            'driver' => 'daily',
            'path' => storage_path('logs/accounts.log'),
            'level' => 'info',
            'days' => 90,
            'tap' => [JsonFormatterFactory::class],
        ],

        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'warning',
            'days' => 365,
            'tap' => [JsonFormatterFactory::class],
        ],

        // ---------------- Laravel housekeeping channels --------------------
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'DigitalBank'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/emergency.log'),
        ],

    ],

];
