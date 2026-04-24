<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function (): void {
    cache()->flush();
});

it('emits structured JSON records from the transfers channel', function (): void {
    $logFile = storage_path('logs/test-json.log');
    @unlink($logFile);

    // Build a one-off JSON logger writing to our temp file and verify
    // the JsonFormatterFactory produces a valid JSON envelope.
    $logger = new Monolog\Logger('transfers');
    $handler = new Monolog\Handler\StreamHandler($logFile, Monolog\Level::Debug);
    $logger->pushHandler($handler);

    (new App\Logging\JsonFormatterFactory())($logger);

    $logger->info('Transfer completed', [
        'transaction_id' => 'abc',
        'user_id' => 'u1',
        'amount' => '1000.0000',
        'duration_ms' => 145,
    ]);

    $content = trim((string) file_get_contents($logFile));
    expect($content)->toBeString()->and($content)->not->toBe('');

    $decoded = json_decode($content, true);
    expect($decoded)->toBeArray()
        ->and($decoded['channel'])->toBe('transfers')
        ->and($decoded['level_name'])->toBe('INFO')
        ->and($decoded['message'])->toBe('Transfer completed')
        ->and($decoded['context']['amount'])->toBe('1000.0000');

    @unlink($logFile);
});

it('has registered the business-specific log channels from SPEC §10.1', function (): void {
    foreach (['transfers', 'auth', 'accounts', 'security'] as $name) {
        expect(config("logging.channels.$name"))
            ->toBeArray()
            ->and(config("logging.channels.$name.tap"))
            ->toContain(App\Logging\JsonFormatterFactory::class);
    }
});

it('cleanup command deletes only expired idempotency keys', function (): void {
    $user = User::create([
        'first_name' => 'A', 'last_name' => 'B',
        'email' => 'cleanup-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);

    IdempotencyKey::create([
        'key' => (string) Str::uuid(),
        'user_id' => $user->id,
        'endpoint' => 'POST api/x',
        'response_status' => 200,
        'response_body' => ['ok' => true],
        'expires_at' => now()->subHour(),
    ]);
    IdempotencyKey::create([
        'key' => (string) Str::uuid(),
        'user_id' => $user->id,
        'endpoint' => 'POST api/x',
        'response_status' => 200,
        'response_body' => ['ok' => true],
        'expires_at' => now()->addHours(12),
    ]);

    $this->artisan('digitalbank:idempotency:cleanup')->assertExitCode(0);

    expect(IdempotencyKey::count())->toBe(1);
});

it('schedules the cleanup command hourly', function (): void {
    $schedule = app(Illuminate\Console\Scheduling\Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'digitalbank:idempotency:cleanup'));

    expect($events)->not->toBeEmpty();
    expect($events->first()->expression)->toBe('0 * * * *');
});
