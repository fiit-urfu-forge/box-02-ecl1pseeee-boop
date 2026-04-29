<?php

declare(strict_types=1);

use App\Models\AuditLogEntry;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    cache()->flush();
    RateLimiter::clear('login:failed:127.0.0.1|alice@test.local');
    Notification::fake();
});

it('registers a user and queues the verification email', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@test.local',
        'password' => 'Str0ng!Pass',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.email', 'alice@test.local')
        ->assertJsonPath('data.status', 'active');

    expect(User::where('email', 'alice@test.local')->exists())->toBeTrue();
    Notification::assertSentTo(
        User::where('email', 'alice@test.local')->first(),
        VerifyEmailNotification::class,
    );
    expect(AuditLogEntry::where('action', 'user.registered')->count())->toBe(1);
});

it('rejects registration with a weak password', function (): void {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Bob', 'last_name' => 'B',
        'email' => 'bob@test.local',
        'password' => 'abc',
    ])->assertStatus(422)
      ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(User::count())->toBe(0);
});

it('rejects duplicate email', function (): void {
    User::create([
        'first_name' => 'Exists', 'last_name' => 'X',
        'email' => 'dup@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);

    $this->postJson('/api/auth/register', [
        'first_name' => 'A', 'last_name' => 'B',
        'email' => 'dup@test.local',
        'password' => 'Str0ng!Pass',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('requires verified email to log in', function (): void {
    User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'alice@test.local',
        'password' => 'Str0ng!Pass',
    ])->assertStatus(403)
      ->assertJsonPath('error.code', 'FORBIDDEN')
      ->assertJsonPath('error.details.reason', 'email_not_verified');
});

it('logs in a verified user and opens a session', function (): void {
    $user = User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'alice@test.local',
        'password' => 'Str0ng!Pass',
    ]);

    $response->assertOk()->assertJsonPath('data.id', $user->id);
    expect(AuditLogEntry::where('action', 'user.login')->count())->toBe(1);

    // Follow-up authenticated request using the same session.
    $this->getJson('/api/user/profile')->assertOk()->assertJsonPath('data.email', 'alice@test.local');
});

it('increments failed_login_count and blocks after 5 failed attempts', function (): void {
    $user = User::create([
        'first_name' => 'Alice', 'last_name' => 'S',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    for ($i = 1; $i <= 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => 'alice@test.local',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    // 6th attempt: lockout.
    $this->postJson('/api/auth/login', [
        'email' => 'alice@test.local',
        'password' => 'wrong',
    ])->assertStatus(429)
      ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');

    expect($user->fresh()->failed_login_count)->toBe(5);
});

it('successful login clears failed_login_count', function (): void {
    $user = User::create([
        'first_name' => 'Alice', 'last_name' => 'S',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
        'failed_login_count' => 3,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'alice@test.local',
        'password' => 'Str0ng!Pass',
    ])->assertOk();

    expect($user->fresh()->failed_login_count)->toBe(0);
});

it('logs out the current session', function (): void {
    $user = User::create([
        'first_name' => 'Alice', 'last_name' => 'S',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'alice@test.local',
        'password' => 'Str0ng!Pass',
    ])->assertOk();

    $this->postJson('/api/auth/logout')->assertOk()
        ->assertJsonPath('data.logged_out', true);

    // Test runner reuses the app singleton across HTTP calls, so the Auth
    // manager caches the once-authenticated user even after session flush.
    // Simulate a fresh browser by forgetting guard state and cookies.
    Illuminate\Support\Facades\Auth::forgetGuards();
    $this->flushSession();

    $this->getJson('/api/user/profile')->assertStatus(401);
});

it('verifies a signed email link and refuses tampered ones', function (): void {
    $user = User::create([
        'first_name' => 'Alice', 'last_name' => 'S',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
    ]);

    $validUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->getJson($validUrl)->assertOk()->assertJsonPath('data.verified', true);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect(AuditLogEntry::where('action', 'user.email_verified')->count())->toBe(1);

    // Tamper with the hash.
    $bad = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => 'deadbeef'],
    );
    $this->getJson($bad)->assertStatus(403);
});
