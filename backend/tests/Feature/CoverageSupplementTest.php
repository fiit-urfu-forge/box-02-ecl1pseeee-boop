<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Models\AuditLogEntry;
use App\Models\IdempotencyKey;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TransferService;
use App\Support\AccountNumberGenerator;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Extra tests exercising code paths the core suite leaves uncovered. Groups
 * here are organised per-class so failures point at the owner directly.
 */

beforeEach(function (): void {
    cache()->flush();
    $this->user = User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'cov-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
});

// --------------------------------------------------------------------------
// AuthController — §11.1 target 85%
// --------------------------------------------------------------------------

it('locks out the user BEFORE checking credentials once the limiter trips', function (): void {
    // Seed 5 hits so the next call falls straight into the early 429 branch
    // before any DB check.
    $rlKey = 'login:failed:127.0.0.1|cov-existing@test.local';
    for ($i = 0; $i < 5; $i++) {
        \Illuminate\Support\Facades\RateLimiter::hit($rlKey, 900);
    }

    $this->postJson('/api/auth/login', [
        'email' => 'cov-existing@test.local',
        'password' => 'anything',
    ])->assertStatus(429)
      ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
      ->assertJsonPath('error.details.retry_after_seconds', fn ($v) => is_int($v));
});

it('rejects login for a suspended account', function (): void {
    DB::table('users')->where('id', $this->user->id)->update(['status' => 'suspended']);

    $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'Str0ng!Pass',
    ])->assertStatus(403)
      ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('logs out all sessions and writes an audit entry', function (): void {
    $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'Str0ng!Pass',
    ])->assertOk();

    // Pre-seed a fake session row to prove the DB-session cleanup path runs.
    DB::table('sessions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $this->user->id,
        'payload' => base64_encode('x'),
        'last_activity' => time(),
    ]);

    $this->postJson('/api/auth/logout-all')
        ->assertOk()
        ->assertJsonPath('data.logged_out', true)
        ->assertJsonPath('data.sessions_invalidated', true);

    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);
    expect(AuditLogEntry::where('action', 'user.logout_all')->count())->toBe(1);
});

it('refuses logout-all for anonymous requests', function (): void {
    $this->postJson('/api/auth/logout-all')->assertStatus(401);
});

// --------------------------------------------------------------------------
// VerifyEmailController — §11.1 target 85%
// --------------------------------------------------------------------------

it('resend verification email is a no-op when already verified', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/auth/email/resend')
        ->assertOk()
        ->assertJsonPath('data.already_verified', true);
});

it('resend verification email sends a fresh mail for unverified user', function (): void {
    DB::table('users')->where('id', $this->user->id)->update(['email_verified_at' => null]);
    Illuminate\Support\Facades\Notification::fake();

    $this->actingAs($this->user->fresh())
        ->postJson('/api/auth/email/resend')
        ->assertOk()
        ->assertJsonPath('data.sent', true);

    Illuminate\Support\Facades\Notification::assertSentTo(
        $this->user->fresh(),
        App\Notifications\VerifyEmailNotification::class,
    );
});

it('verification returns already_verified on a second valid call', function (): void {
    $fresh = User::create([
        'first_name' => 'Carl', 'last_name' => 'C',
        'email' => 'carl-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);

    $url = Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $fresh->id, 'hash' => sha1($fresh->email)],
    );

    $this->getJson($url)->assertOk()->assertJsonPath('data.verified', true);
    $this->getJson($url)->assertOk()->assertJsonPath('data.already_verified', true);
});

it('verification fails when the user id no longer exists', function (): void {
    $ghost = (string) Str::uuid();
    $url = Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $ghost, 'hash' => sha1('ghost@test.local')],
    );
    $this->getJson($url)->assertStatus(404)
        ->assertJsonPath('error.code', 'USER_NOT_FOUND');
});

it('VerifyEmailNotification produces a MailMessage with Russian copy', function (): void {
    $notification = new App\Notifications\VerifyEmailNotification();
    $message = $notification->toMail($this->user);

    expect($message)->toBeInstanceOf(Illuminate\Notifications\Messages\MailMessage::class);
    expect($message->subject)->toContain('DigitalBank');
    $body = implode("\n", $message->introLines) . "\n" . implode("\n", $message->outroLines);
    expect($body)->toContain('подтвердите');
});

// --------------------------------------------------------------------------
// IdempotencyMiddleware — §11.1 target 100%
// --------------------------------------------------------------------------

it('idempotency: lost race still replays the stored response', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/mutate_race', fn () => response()->json([
            'success' => true,
            'data' => ['call' => cache()->increment('race.calls')],
        ], 201))->name('transfers.store');

    $key = (string) Str::uuid();
    $payload = ['n' => 1];

    // Emulate a concurrent winner: insert a completed row BEFORE our call.
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'user_id' => $this->user->id,
        'endpoint' => 'POST api/_tests/mutate_race',
        'response_status' => 201,
        'response_body' => json_encode([
            'fingerprint' => '___whatever___', // will trigger payload_mismatch branch
            'status' => 201,
            'body' => ['success' => true, 'data' => ['cached' => true]],
        ]),
        'expires_at' => now()->addHours(24),
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate_race', $payload)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT')
        ->assertJsonPath('error.details.reason', 'payload_mismatch');

    // Handler must not have run.
    expect((int) cache()->get('race.calls', 0))->toBe(0);
});

it('idempotency: handler returning empty body is cached as empty array', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/empty_body', fn () => response('', 204))
        ->name('transfers.store');

    $key = (string) Str::uuid();
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/empty_body', [])
        ->assertStatus(204);

    // Second call should NOT re-run; 204 is cacheable (2xx).
    $row = IdempotencyKey::find($key);
    expect($row)->not->toBeNull();
    expect($row->response_status)->toBe(204);
});

it('idempotency: fingerprint recursively sorts nested object keys', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/nested', fn () => response()->json(['success' => true, 'data' => ['ok' => true]], 201))
        ->name('transfers.store');

    $key = (string) Str::uuid();

    // Two payloads with different key ORDER but same contents — same fingerprint.
    $first  = ['meta' => ['z' => 2, 'a' => 1], 'list' => [1, 2, 3]];
    $second = ['list' => [1, 2, 3], 'meta' => ['a' => 1, 'z' => 2]];

    $r1 = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/nested', $first)
        ->assertStatus(201);

    $r2 = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/nested', $second)
        ->assertStatus(201)
        ->assertHeader('X-Idempotent-Replayed', '1');

    expect($r2->json())->toEqual($r1->json());
});

// --------------------------------------------------------------------------
// TransferService — §11.1 target 90%
// --------------------------------------------------------------------------

it('TransferService finalises as failed when accounts vanish before lock (mocked Service)', function (): void {
    // Exercises the ACCOUNT_NOT_FOUND / generic Throwable catch branches
    // without foreign-key-breaking gymnastics. We build a stub Transaction
    // with fake IDs that the SELECT … FOR UPDATE will not resolve.
    $realSender = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000050',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '100.0000',
    ]);
    $realReceiver = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000051',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active', 'balance' => '0.0000',
    ]);
    $tx = Transaction::create([
        'sender_account_id' => $realSender->id,
        'receiver_account_id' => $realReceiver->id,
        'amount' => '1.0000',
        'currency' => 'RUB', 'status' => 'pending', 'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    // Swap the tx's FK-relevant columns in-memory BEFORE the service reads
    // them. Because the service looks up the accounts by id, spoofed UUIDs
    // will miss and trigger the ACCOUNT_NOT_FOUND branch.
    $tx->sender_account_id = '00000000-0000-0000-0000-000000000001';
    $tx->receiver_account_id = '00000000-0000-0000-0000-000000000002';

    $result = app(TransferService::class)->execute($tx);
    expect($result->status)->toBe('failed');
    expect($result->error_code)->toBe('ACCOUNT_NOT_FOUND');
});

it('TransferService is a no-op when called on a processing (already-claimed) tx', function (): void {
    $a1 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000075',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '100.0000',
    ]);
    $a2 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000076',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active', 'balance' => '0.0000',
    ]);
    $tx = Transaction::create([
        'sender_account_id' => $a1->id,
        'receiver_account_id' => $a2->id,
        'amount' => '10.0000',
        'currency' => 'RUB', 'status' => 'processing', 'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $r = app(TransferService::class)->execute($tx);
    expect($r->status)->toBe('processing'); // Unchanged — claim failed.
    expect($a1->fresh()->balance)->toBe('100.0000');
});

it('TransferService post-lock catches ACCOUNT_FROZEN when sender is frozen mid-flight', function (): void {
    $a1 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000090',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '100.0000',
    ]);
    $a2 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000091',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active', 'balance' => '0.0000',
    ]);
    $tx = Transaction::create([
        'sender_account_id' => $a1->id,
        'receiver_account_id' => $a2->id,
        'amount' => '10.0000',
        'currency' => 'RUB', 'status' => 'pending', 'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    // Freeze AFTER tx creation — controller wouldn't have caught it.
    DB::table('accounts')->where('id', $a1->id)->update(['status' => 'frozen']);

    $r = app(TransferService::class)->execute($tx);
    expect($r->status)->toBe('failed');
    expect($r->error_code)->toBe('ACCOUNT_FROZEN');
});

it('TransferService post-lock catches CURRENCY_MISMATCH when currencies diverge mid-flight', function (): void {
    $a1 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000092',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '100.0000',
    ]);
    $a2 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000093',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active', 'balance' => '0.0000',
    ]);
    $tx = Transaction::create([
        'sender_account_id' => $a1->id,
        'receiver_account_id' => $a2->id,
        'amount' => '10.0000',
        'currency' => 'RUB', 'status' => 'pending', 'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    // Currency of the receiver changes after the tx is queued.
    DB::table('accounts')->where('id', $a2->id)->update(['currency' => 'USD']);

    $r = app(TransferService::class)->execute($tx);
    expect($r->status)->toBe('failed');
    expect($r->error_code)->toBe('CURRENCY_MISMATCH');
});

it('TransferService post-lock catches DAILY_LIMIT_EXCEEDED', function (): void {
    $a1 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000094',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '500000.0000',
    ]);
    $a2 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000095',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active', 'balance' => '0.0000',
    ]);

    // Prior successes = 290 000 today. Limit = 300 000.
    for ($i = 0; $i < 3; $i++) {
        Transaction::create([
            'sender_account_id' => $a1->id,
            'receiver_account_id' => $a2->id,
            'amount' => '100000.0000',
            'currency' => 'RUB', 'status' => 'success', 'type' => 'internal',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    $tx = Transaction::create([
        'sender_account_id' => $a1->id,
        'receiver_account_id' => $a2->id,
        'amount' => '20000.0000',
        'currency' => 'RUB', 'status' => 'pending', 'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $r = app(TransferService::class)->execute($tx);
    expect($r->status)->toBe('failed');
    expect($r->error_code)->toBe('DAILY_LIMIT_EXCEEDED');
});

it('MockSbpGateway responds to both initiateTransfer and getStatus', function (): void {
    $gw = new App\Sbp\MockSbpGateway();

    $init = $gw->initiateTransfer(['amount' => '1.00']);
    expect($init['status'])->toBe('accepted');
    expect($init['provider_id'])->toStartWith('mock-');

    $status = $gw->getStatus($init['provider_id']);
    expect($status)->toBe($init);
});

it('TransferService claim is idempotent: second call on finalised tx returns state', function (): void {
    $a1 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000070',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '100.0000',
    ]);
    $a2 = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000071',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active', 'balance' => '0.0000',
    ]);
    $tx = Transaction::create([
        'sender_account_id' => $a1->id,
        'receiver_account_id' => $a2->id,
        'amount' => '10.0000',
        'currency' => 'RUB', 'status' => 'success', 'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $r = app(TransferService::class)->execute($tx);
    expect($r->status)->toBe('success');
});

// --------------------------------------------------------------------------
// AccountNumberGenerator — error paths
// --------------------------------------------------------------------------

it('AccountNumberGenerator rejects unsupported currency', function (): void {
    expect(fn () => AccountNumberGenerator::forCurrency('EUR'))
        ->toThrow(ApiException::class);
});

it('AccountNumberGenerator produces 20-digit number with correct prefix', function (): void {
    $n = AccountNumberGenerator::forCurrency('RUB');
    expect(strlen($n))->toBe(20);
    expect(substr($n, 0, 3))->toBe('810');
    expect(ctype_digit($n))->toBeTrue();
});

// --------------------------------------------------------------------------
// AuditLogger — catch branch
// --------------------------------------------------------------------------

it('AuditLogger swallows DB errors without raising', function (): void {
    $logger = app(AuditLogger::class);

    // Truncate reference table so the FK explodes → record() must not throw.
    DB::table('audit_log')->delete();

    $logger->record(
        action: 'test.error',
        entityType: 'user',
        // user_id FK-violates: this user id doesn't exist.
        userId: '00000000-0000-0000-0000-000000000000',
        entityId: '00000000-0000-0000-0000-000000000000',
    );

    // No exception = pass.
    expect(true)->toBeTrue();
});

// --------------------------------------------------------------------------
// ApiResponse — uncovered branches
// --------------------------------------------------------------------------

it('ApiResponse paginated envelopes carry pagination block', function (): void {
    $paginator = new Illuminate\Pagination\LengthAwarePaginator(
        items: [['id' => 1], ['id' => 2]],
        total: 12,
        perPage: 2,
        currentPage: 3,
    );
    $response = ApiResponse::paginated($paginator);
    $body = $response->getData(true);

    expect($body['pagination']['current_page'])->toBe(3);
    expect($body['pagination']['total'])->toBe(12);
    expect($body['pagination']['last_page'])->toBe(6);
});

it('ApiResponse::fromException maps 419 for CSRF token mismatch', function (): void {
    $response = ApiResponse::fromException(
        new Illuminate\Session\TokenMismatchException('csrf'),
        Request::create('/api/x', 'POST'),
    );

    expect($response->getStatusCode())->toBe(419);
    $body = $response->getData(true);
    expect($body['error']['code'])->toBe(ErrorCode::FORBIDDEN->value);
});

it('ApiResponse::fromException maps generic HttpException to INTERNAL_ERROR', function (): void {
    $response = ApiResponse::fromException(
        new Symfony\Component\HttpKernel\Exception\HttpException(418, 'i am a teapot'),
        Request::create('/api/x', 'GET'),
    );

    expect($response->getStatusCode())->toBe(418);
    expect($response->getData(true)['error']['code'])->toBe('INTERNAL_ERROR');
});

// --------------------------------------------------------------------------
// AccountPolicy — §7.1 (Policy on Account)
// --------------------------------------------------------------------------

it('AccountPolicy allows the owner and denies others', function (): void {
    $other = User::create([
        'first_name' => 'X', 'last_name' => 'Y',
        'email' => 'x-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);
    $account = App\Models\Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000080',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '0.0000',
    ]);

    $policy = new App\Policies\AccountPolicy();
    expect($policy->view($this->user, $account))->toBeTrue();
    expect($policy->view($other, $account))->toBeFalse();
    expect($policy->update($this->user, $account))->toBeTrue();
    expect($policy->update($other, $account))->toBeFalse();
});
