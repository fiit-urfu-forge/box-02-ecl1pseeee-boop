<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLogEntry;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    cache()->flush();
    $this->user = User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'alice-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
});

it('returns 401 for unauthenticated list', function (): void {
    $this->getJson('/api/accounts')->assertStatus(401);
});

it('creates an RUB account with 810-prefixed 20-digit number', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/accounts', ['currency' => 'RUB', 'type' => 'checking']);

    $response->assertStatus(201)
        ->assertJsonPath('data.currency', 'RUB')
        ->assertJsonPath('data.type', 'checking')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.balance', '0.0000');

    $number = $response->json('data.account_number');
    expect($number)->toBeString()
        ->and(strlen($number))->toBe(20)
        ->and(substr($number, 0, 3))->toBe('810');

    expect(AuditLogEntry::where('action', 'account.created')->count())->toBe(1);
});

it('creates a USD account with 840-prefixed number', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/accounts', ['currency' => 'USD', 'type' => 'savings']);

    $response->assertStatus(201)->assertJsonPath('data.currency', 'USD');
    expect(substr($response->json('data.account_number'), 0, 3))->toBe('840');
});

it('rejects unsupported currency', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/accounts', ['currency' => 'EUR', 'type' => 'checking'])
        ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('rejects invalid type', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/accounts', ['currency' => 'RUB', 'type' => 'deposit'])
        ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('enforces the 5-accounts-per-user limit', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($this->user)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/accounts', ['currency' => 'RUB', 'type' => 'checking'])
            ->assertStatus(201);
    }

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/accounts', ['currency' => 'RUB', 'type' => 'checking'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.limit', 5);
});

it('requires X-Idempotency-Key for creation', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/accounts', ['currency' => 'RUB', 'type' => 'checking'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('lists only the current user accounts', function (): void {
    $mine = Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000001',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '100.0000',
    ]);

    $other = User::create([
        'first_name' => 'Bob', 'last_name' => 'B',
        'email' => 'bob-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active', 'email_verified_at' => now(),
    ]);
    Account::create([
        'user_id' => $other->id,
        'account_number' => '81000000000000000002',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '0.0000',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/accounts')->assertOk();

    $ids = array_column($response->json('data'), 'id');
    expect($ids)->toBe([$mine->id]);
});

it('shows an owned account and hides others as NOT_FOUND', function (): void {
    $mine = Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000003',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '0.0000',
    ]);

    $other = User::create([
        'first_name' => 'Bob', 'last_name' => 'B',
        'email' => 'bob-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active', 'email_verified_at' => now(),
    ]);
    $hidden = Account::create([
        'user_id' => $other->id,
        'account_number' => '81000000000000000004',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '0.0000',
    ]);

    $this->actingAs($this->user)->getJson("/api/accounts/{$mine->id}")
        ->assertOk()->assertJsonPath('data.id', $mine->id);

    $this->actingAs($this->user)->getJson("/api/accounts/{$hidden->id}")
        ->assertStatus(404)->assertJsonPath('error.code', 'ACCOUNT_NOT_FOUND');
});

it('replays idempotent account creation without a second row', function (): void {
    $key = (string) Str::uuid();
    $payload = ['currency' => 'RUB', 'type' => 'checking'];

    $r1 = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/accounts', $payload)
        ->assertStatus(201);

    $r2 = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/accounts', $payload)
        ->assertStatus(201)
        ->assertHeader('X-Idempotent-Replayed', '1');

    expect($r2->json('data.id'))->toBe($r1->json('data.id'));
    expect(Account::where('user_id', $this->user->id)->count())->toBe(1);
});

it('artisan command toggles account status and writes audit_log', function (): void {
    $account = Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000099',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active', 'balance' => '0.0000',
    ]);

    $this->artisan('digitalbank:account:set-status', [
        'id' => $account->id,
        'status' => 'frozen',
        '--reason' => 'fraud investigation',
    ])->assertExitCode(0);

    expect($account->fresh()->status)->toBe('frozen');
    expect(AuditLogEntry::where('action', 'account.frozen')->count())->toBe(1);
});
