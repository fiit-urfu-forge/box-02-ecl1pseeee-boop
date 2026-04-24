<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    cache()->flush();
    $this->user = User::create([
        'first_name' => 'Alice', 'last_name' => 'Smith',
        'email' => 'alice@test.local',
        'password_hash' => password_hash('Str0ng!Pass', PASSWORD_BCRYPT, ['cost' => 4]),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
});

it('returns 401 for anonymous profile access', function (): void {
    $this->getJson('/api/user/profile')->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('returns the current user profile', function (): void {
    $this->actingAs($this->user)->getJson('/api/user/profile')
        ->assertOk()
        ->assertJsonPath('data.email', 'alice@test.local')
        ->assertJsonPath('data.first_name', 'Alice');
});

it('patches allowed profile fields and records an audit entry', function (): void {
    $this->actingAs($this->user)->patchJson('/api/user/profile', [
        'first_name' => 'Alina',
        'phone' => '+79001234567',
    ])->assertOk()
      ->assertJsonPath('data.first_name', 'Alina')
      ->assertJsonPath('data.phone', '+79001234567');

    expect($this->user->fresh()->first_name)->toBe('Alina');
});

it('rejects invalid phone formats', function (): void {
    $this->actingAs($this->user)->patchJson('/api/user/profile', [
        'phone' => '89001234567', // missing + prefix
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('uploads and serves an avatar, replacing any previous file', function (): void {
    Storage::fake('local');

    $first = $this->actingAs($this->user)->postJson('/api/user/avatar', [
        'avatar' => UploadedFile::fake()->image('a.jpg', 10, 10),
    ])->assertOk()->json();

    $firstPath = $this->user->fresh()->avatar_path;
    expect($firstPath)->not->toBeNull();
    Storage::disk('local')->assertExists($firstPath);
    expect($first['data']['avatar_url'])->toContain('/api/user/avatar');

    // Upload again — old file must be removed.
    $this->actingAs($this->user)->postJson('/api/user/avatar', [
        'avatar' => UploadedFile::fake()->image('b.png', 10, 10),
    ])->assertOk();

    Storage::disk('local')->assertMissing($firstPath);
    $secondPath = $this->user->fresh()->avatar_path;
    Storage::disk('local')->assertExists($secondPath);
    expect($secondPath)->not->toBe($firstPath);
});

it('rejects unsupported avatar mime types', function (): void {
    Storage::fake('local');

    $this->actingAs($this->user)->postJson('/api/user/avatar', [
        'avatar' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('rejects oversize avatar', function (): void {
    Storage::fake('local');

    // 6 MB — limit is 5 MB in phpunit env.
    $this->actingAs($this->user)->postJson('/api/user/avatar', [
        'avatar' => UploadedFile::fake()->create('big.jpg', 6144, 'image/jpeg'),
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('streams the avatar through the show endpoint', function (): void {
    Storage::fake('local');

    $this->actingAs($this->user)->postJson('/api/user/avatar', [
        'avatar' => UploadedFile::fake()->image('a.jpg', 10, 10),
    ])->assertOk();

    $this->actingAs($this->user)
        ->get('/api/user/avatar')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('returns NOT_FOUND when user has no avatar', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/user/avatar')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});
