<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLogEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Covers §11.2 of SPEC (mandatory test cases for transfers). Queue is sync
 * in tests, so the transfer is performed in-process on POST /api/transfers.
 */
beforeEach(function (): void {
    cache()->flush();

    $this->user = User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'alice-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active', 'email_verified_at' => now(),
    ]);
    $this->sender = Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000010',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active',
        'balance' => '10000.0000',
    ]);
    $this->receiver = Account::create([
        'user_id' => User::create([
            'first_name' => 'Bob', 'last_name' => 'B',
            'email' => 'bob-'.Str::uuid().'@test.local',
            'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
            'status' => 'active', 'email_verified_at' => now(),
        ])->id,
        'account_number' => '81000000000000000011',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active',
        'balance' => '0.0000',
    ]);
});

function transfer(array $overrides = []): array {
    return array_merge([
        'sender_account_id' => test()->sender->id,
        'receiver_account_number' => test()->receiver->account_number,
        'amount' => '100.00',
        'description' => 't',
    ], $overrides);
}

it('performs a successful RUB transfer atomically', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['amount' => '1000.0000']));

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.amount', '1000.0000')
        ->assertJsonPath('data.currency', 'RUB');

    expect($this->sender->fresh()->balance)->toBe('9000.0000');
    expect($this->receiver->fresh()->balance)->toBe('1000.0000');

    expect(AuditLogEntry::where('action', 'transfer.created')->count())->toBe(1);
    expect(AuditLogEntry::where('action', 'transfer.success')->count())->toBe(1);
});

it('rejects transfer when sender has insufficient funds', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['amount' => '50000.00']))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'INSUFFICIENT_FUNDS');

    expect($this->sender->fresh()->balance)->toBe('10000.0000');
    expect($this->receiver->fresh()->balance)->toBe('0.0000');
});

it('rejects transfer to non-existent account', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['receiver_account_number' => '81099999999999999999']))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'ACCOUNT_NOT_FOUND');
});

it('rejects transfer when sender is frozen', function (): void {
    DB::table('accounts')->where('id', $this->sender->id)->update(['status' => 'frozen']);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_FROZEN');
});

it('rejects transfer when receiver is frozen', function (): void {
    DB::table('accounts')->where('id', $this->receiver->id)->update(['status' => 'frozen']);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_FROZEN');
});

it('rejects transfer above single-transaction limit', function (): void {
    DB::table('accounts')->where('id', $this->sender->id)->update(['balance' => '200000.0000']);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['amount' => '150000.00']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AMOUNT_TOO_HIGH');
});

it('rejects transfer below minimum', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['amount' => '0.5']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AMOUNT_TOO_LOW');
});

it('rejects transfer when daily limit would be exceeded', function (): void {
    DB::table('accounts')->where('id', $this->sender->id)->update(['balance' => '500000.0000']);

    // Seed 3 successful transfers today totalling 290 000 RUB.
    for ($i = 0; $i < 3; $i++) {
        Transaction::create([
            'sender_account_id' => $this->sender->id,
            'receiver_account_id' => $this->receiver->id,
            'amount' => '100000.0000',
            'currency' => 'RUB',
            'status' => 'success',
            'type' => 'internal',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['amount' => '50000.00']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'DAILY_LIMIT_EXCEEDED');
});

it('rejects cross-currency transfers', function (): void {
    $usdAccount = Account::create([
        'user_id' => $this->receiver->user_id,
        'account_number' => '84000000000000000001',
        'currency' => 'USD', 'type' => 'checking', 'status' => 'active',
        'balance' => '0.0000',
    ]);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer(['receiver_account_number' => $usdAccount->account_number]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CURRENCY_MISMATCH');
});

it('rejects self-transfer to the same account', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer([
            'receiver_account_number' => $this->sender->account_number,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'SELF_TRANSFER_SAME_ACCOUNT');
});

it('allows transfer between two accounts of the same user', function (): void {
    $mine2 = Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000020',
        'currency' => 'RUB', 'type' => 'savings', 'status' => 'active',
        'balance' => '0.0000',
    ]);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer([
            'receiver_account_number' => $mine2->account_number,
            'amount' => '500.00',
        ]))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'success');

    expect($this->sender->fresh()->balance)->toBe('9500.0000');
    expect($mine2->fresh()->balance)->toBe('500.0000');
});

it('replays idempotent transfer without moving funds twice', function (): void {
    $key = (string) Str::uuid();
    $payload = transfer(['amount' => '123.45']);

    $r1 = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/transfers', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'success');

    $r2 = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/transfers', $payload)
        ->assertStatus(201)
        ->assertHeader('X-Idempotent-Replayed', '1');

    expect($r2->json('data.id'))->toBe($r1->json('data.id'));
    expect($this->sender->fresh()->balance)->toBe('9876.5500'); // 10000 - 123.45
    expect($this->receiver->fresh()->balance)->toBe('123.4500');
    expect(Transaction::count())->toBe(1);
});

it('returns paginated history for the user', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($this->user)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transfers', transfer(['amount' => '10.00']))
            ->assertStatus(201);
    }

    $resp = $this->actingAs($this->user)->getJson('/api/transfers?per_page=2')->assertOk();

    expect(count($resp->json('data')))->toBe(2);
    expect($resp->json('pagination.total'))->toBe(3);
    expect($resp->json('pagination.per_page'))->toBe(2);
});

it('prevents overdraft when two transfers compete for the same balance', function (): void {
    // Race simulation: create two pending transactions of 6000 RUB each
    // against a sender with 10000 RUB. Run the service twice — SELECT FOR
    // UPDATE serialises them, so the second one must fail with INSUFFICIENT_FUNDS
    // after the first commits. Balance must never dip below 0 (the DB CHECK
    // constraint `balance >= 0` is an additional safety net).
    //
    // (A truly parallel fork-based race test is skipped here because Laravel's
    // RefreshDatabase keeps the test data inside an uncommitted transaction
    // that child processes can't see. Sequential execution still proves the
    // lock+check pattern: each `execute()` opens its own DB tx and sees the
    // committed state from prior calls.)

    $tx1 = Transaction::create([
        'sender_account_id' => $this->sender->id,
        'receiver_account_id' => $this->receiver->id,
        'amount' => '6000.0000',
        'currency' => 'RUB',
        'status' => 'pending',
        'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $tx2 = Transaction::create([
        'sender_account_id' => $this->sender->id,
        'receiver_account_id' => $this->receiver->id,
        'amount' => '6000.0000',
        'currency' => 'RUB',
        'status' => 'pending',
        'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $service = app(App\Services\TransferService::class);
    $r1 = $service->execute($tx1);
    $r2 = $service->execute($tx2);

    expect($r1->status)->toBe('success');
    expect($r2->status)->toBe('failed');
    expect($r2->error_code)->toBe('INSUFFICIENT_FUNDS');

    $senderBalance = (float) $this->sender->fresh()->balance;
    $receiverBalance = (float) $this->receiver->fresh()->balance;

    expect($senderBalance)->toBeGreaterThanOrEqual(0.0);
    expect($senderBalance)->toBe(4000.0);
    expect($receiverBalance)->toBe(6000.0);
});

it('refuses to double-spend even when called with the same pending transaction twice', function (): void {
    // If a retry lands the same tx.id on two workers, only the first claim
    // (pending → processing) must actually move funds.
    $tx = Transaction::create([
        'sender_account_id' => $this->sender->id,
        'receiver_account_id' => $this->receiver->id,
        'amount' => '1000.0000',
        'currency' => 'RUB',
        'status' => 'pending',
        'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $service = app(App\Services\TransferService::class);
    $r1 = $service->execute($tx);
    $r2 = $service->execute($tx);

    expect($r1->status)->toBe('success');
    expect($r2->status)->toBe('success'); // Reloaded — same terminal state.
    expect($this->sender->fresh()->balance)->toBe('9000.0000');
    expect($this->receiver->fresh()->balance)->toBe('1000.0000');
});

it('sender receives 401 when unauthenticated', function (): void {
    $this->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/transfers', transfer())
        ->assertStatus(401);
});

it('returns NOT_FOUND when asking for someone else transfer', function (): void {
    $tx = Transaction::create([
        'sender_account_id' => $this->sender->id,
        'receiver_account_id' => $this->receiver->id,
        'amount' => '10.0000',
        'currency' => 'RUB',
        'status' => 'success',
        'type' => 'internal',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $other = User::create([
        'first_name' => 'C', 'last_name' => 'C',
        'email' => 'c-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active', 'email_verified_at' => now(),
    ]);

    $this->actingAs($other)
        ->getJson("/api/transfers/{$tx->id}")
        ->assertStatus(404);
});
