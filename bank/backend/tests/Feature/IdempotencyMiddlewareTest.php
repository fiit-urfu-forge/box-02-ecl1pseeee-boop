<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Covers §7.2 of SPEC. Aim for 100% coverage of IdempotencyMiddleware.
 *
 * We mount a throwaway route guarded by `auth:sanctum` + `idempotency` and
 * use `actingAs()` so the middleware sees a user. The handler increments a
 * counter stored in the cache — that way we can assert it ran ONCE even
 * under retries.
 */
beforeEach(function (): void {
    $this->user = User::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test-'.Str::uuid().'@local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);

    // Test route: records a call counter so we can assert idempotency.
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/mutate', function (Illuminate\Http\Request $r) {
            $counter = cache()->increment('mutate.calls');
            return response()->json([
                'success' => true,
                'data' => ['call' => $counter, 'payload' => $r->input('payload')],
            ], 201);
        })->name('transfers.store');

    cache()->flush();
});

it('rejects missing header on required endpoints', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/_tests/mutate', ['payload' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(IdempotencyKey::count())->toBe(0);
});

it('rejects malformed UUID', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => 'not-a-uuid'])
        ->postJson('/api/_tests/mutate', ['payload' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('runs the handler exactly once and replays the cached body', function (): void {
    $key = (string) Str::uuid();
    $payload = ['payload' => 'alpha'];

    $first = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.call', 1);

    $second = $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', $payload)
        ->assertStatus(201)
        ->assertHeader('X-Idempotent-Replayed', '1')
        ->assertJsonPath('data.call', 1);

    expect($second->json())->toEqual($first->json());
    expect((int) cache()->get('mutate.calls'))->toBe(1);
});

it('raises IDEMPOTENCY_CONFLICT when the same key is reused with different payload', function (): void {
    $key = (string) Str::uuid();

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', ['payload' => 'alpha'])
        ->assertStatus(201);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', ['payload' => 'beta'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});

it('raises IDEMPOTENCY_CONFLICT when another user reuses the key', function (): void {
    $key = (string) Str::uuid();

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', ['payload' => 'alpha'])
        ->assertStatus(201);

    $other = User::create([
        'first_name' => 'Other', 'last_name' => 'U',
        'email' => 'other-'.Str::uuid().'@local',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]),
    ]);

    // Simulate a different browser — the test client shares cookies across
    // actingAs() calls, so Sanctum would otherwise resolve the original user
    // from the stale session cookie.
    $this->flushSession();
    Illuminate\Support\Facades\Auth::forgetGuards();

    $this->actingAs($other)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', ['payload' => 'alpha'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});

it('does not cache non-2xx responses so the client can retry', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/broken', function () {
            cache()->increment('broken.calls');
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'boom'],
            ], 500);
        })->name('broken.store');

    $key = (string) Str::uuid();

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/broken', ['payload' => 'x'])
        ->assertStatus(500);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/broken', ['payload' => 'x'])
        ->assertStatus(500);

    // Handler ran twice because 500s are not cached.
    expect((int) cache()->get('broken.calls'))->toBe(2);
    expect(IdempotencyKey::query()->count())->toBe(0);
});

it('rolls back the reservation when the handler throws', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/crash', function () {
            cache()->increment('crash.calls');
            throw new RuntimeException('boom');
        })->name('crash.store');

    $key = (string) Str::uuid();

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/crash', ['payload' => 'x'])
        ->assertStatus(500);

    // Reservation must be gone so a retry can proceed.
    expect(IdempotencyKey::query()->where('key', $key)->exists())->toBeFalse();

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/crash', ['payload' => 'x'])
        ->assertStatus(500);

    expect((int) cache()->get('crash.calls'))->toBe(2);
});

it('flags an in-flight reservation as CONFLICT', function (): void {
    $key = (string) Str::uuid();

    // Seed a pending row as if another worker is mid-handler.
    DB::table('idempotency_keys')->insert([
        'key' => $key,
        'user_id' => $this->user->id,
        'endpoint' => 'POST api/_tests/mutate',
        'response_status' => 0,
        'response_body' => json_encode(['_pending' => true, 'fingerprint' => 'whatever']),
        'expires_at' => now()->addHours(24),
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => $key])
        ->postJson('/api/_tests/mutate', ['payload' => 'x'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT')
        ->assertJsonPath('error.details.reason', 'in_flight');
});

it('ignores the header for GET requests', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->get('/api/_tests/read', fn () => response()->json(['success' => true, 'data' => ['ok' => true]]));

    $this->actingAs($this->user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->getJson('/api/_tests/read')
        ->assertOk();

    expect(IdempotencyKey::query()->count())->toBe(0);
});

it('allows non-required endpoints without the header', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/api/_tests/optional', fn () => response()->json(['success' => true, 'data' => []]));

    $this->actingAs($this->user)
        ->postJson('/api/_tests/optional')
        ->assertOk();

    expect(IdempotencyKey::query()->count())->toBe(0);
});
