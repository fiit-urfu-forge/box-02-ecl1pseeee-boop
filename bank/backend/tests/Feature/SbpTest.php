<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLogEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    cache()->flush();

    $this->user = User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'alice-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active', 'email_verified_at' => now(),
    ]);

    $this->account = Account::create([
        'user_id' => $this->user->id,
        'account_number' => '81000000000000000030',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active',
        'balance' => '5000.0000',
    ]);
});

it('links a phone number to an account', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/sbp/link-phone', [
            'phone' => '+79001234567',
            'account_id' => $this->account->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.phone', '+79001234567');

    expect($this->user->fresh()->phone)->toBe('+79001234567');
    expect(AuditLogEntry::where('action', 'sbp.phone_linked')->count())->toBe(1);
});

it('rejects linking with bad phone format', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/sbp/link-phone', [
            'phone' => '89001234567', // no +
            'account_id' => $this->account->id,
        ])
        ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('rejects linking to someone else account', function (): void {
    $other = User::create([
        'first_name' => 'B', 'last_name' => 'B',
        'email' => 'b-'.Str::uuid().'@test.local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active', 'email_verified_at' => now(),
    ]);
    $foreign = Account::create([
        'user_id' => $other->id,
        'account_number' => '81000000000000000040',
        'currency' => 'RUB', 'type' => 'checking', 'status' => 'active',
        'balance' => '0.0000',
    ]);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/sbp/link-phone', [
            'phone' => '+79001234567',
            'account_id' => $foreign->id,
        ])
        ->assertStatus(404)->assertJsonPath('error.code', 'ACCOUNT_NOT_FOUND');
});

it('creates a pending sbp_out transaction via the mock gateway', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/sbp/transfer', [
            'sender_account_id' => $this->account->id,
            'receiver_phone' => '+79999999999',
            'amount' => '200.00',
            'description' => 'pizza',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.type', 'sbp_out')
        ->assertJsonPath('data.receiver_phone', '+79999999999')
        ->assertJsonPath('data.provider_status', 'accepted');

    expect(Transaction::where('type', 'sbp_out')->count())->toBe(1);
    // Balance must NOT change for MVP (stub does not move funds).
    expect($this->account->fresh()->balance)->toBe('5000.0000');

    expect(AuditLogEntry::where('action', 'sbp.transfer_initiated')->count())->toBe(1);
});

it('requires X-Idempotency-Key on sbp transfer', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/sbp/transfer', [
            'sender_account_id' => $this->account->id,
            'receiver_phone' => '+79999999999',
            'amount' => '200.00',
        ])
        ->assertStatus(422);
});

it('rejects sbp transfer with amount below minimum', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/sbp/transfer', [
            'sender_account_id' => $this->account->id,
            'receiver_phone' => '+79999999999',
            'amount' => '0.5',
        ])
        ->assertStatus(422)->assertJsonPath('error.code', 'AMOUNT_TOO_LOW');
});

it('webhook rejects request with missing signature', function (): void {
    $this->postJson('/api/webhooks/sbp', ['event' => 'ping'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');

    expect(AuditLogEntry::where('action', 'sbp.webhook_bad_signature')->count())->toBe(1);
});

it('webhook rejects request with wrong signature', function (): void {
    $payload = json_encode(['event' => 'status_update']);
    $this->withHeaders(['X-Sbp-Signature' => 'deadbeef'])
        ->call('POST', '/api/webhooks/sbp', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)
        ->assertStatus(401);
});

it('webhook accepts request with correct HMAC-SHA256 signature', function (): void {
    $secret = config('digitalbank.sbp.webhook_secret');
    $payload = json_encode(['event' => 'status_update', 'provider_id' => 'abc']);
    $sig = hash_hmac('sha256', $payload, $secret);

    $response = $this->call(
        'POST',
        '/api/webhooks/sbp',
        [], [], [],
        [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SBP_SIGNATURE' => $sig,
        ],
        $payload,
    );

    $response->assertOk()->assertJsonPath('data.accepted', true);
    expect(AuditLogEntry::where('action', 'sbp.webhook_received')->count())->toBe(1);
});
