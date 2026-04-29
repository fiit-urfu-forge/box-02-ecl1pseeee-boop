<?php

declare(strict_types=1);

use App\Support\ErrorCode;
use Illuminate\Support\Facades\Route;

it('wraps success responses in the standard envelope', function (): void {
    $response = $this->getJson('/api/ping');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => ['pong' => true],
        ])
        ->assertJsonStructure([
            'success',
            'data',
            'meta' => ['timestamp', 'request_id'],
        ]);

    $body = $response->json();
    expect($body['meta']['request_id'])->toBeString();
    // Request id must be echoed back on the response header.
    expect($response->headers->get('X-Request-Id'))->toBe($body['meta']['request_id']);
});

it('echoes a client-supplied request id when it is a UUID', function (): void {
    $rid = '11111111-2222-3333-4444-555555555555';
    $response = $this->withHeaders(['X-Request-Id' => $rid])->getJson('/api/ping');

    $response->assertOk()->assertJsonPath('meta.request_id', $rid);
    expect($response->headers->get('X-Request-Id'))->toBe($rid);
});

it('generates a fresh request id when the client header is not a UUID', function (): void {
    $response = $this->withHeaders(['X-Request-Id' => 'not-a-uuid'])->getJson('/api/ping');

    $echoed = $response->json('meta.request_id');
    expect($echoed)->not->toBe('not-a-uuid');
    expect($echoed)->toMatch('/^[0-9a-f\-]{36}$/');
});

it('renders domain exceptions through the error envelope', function (): void {
    Route::middleware('api')->get('/api/_tests/boom', function (): void {
        throw new App\Exceptions\ApiException(
            ErrorCode::INSUFFICIENT_FUNDS,
            details: ['available' => '10.0000', 'required' => '100.0000'],
        );
    });

    $response = $this->getJson('/api/_tests/boom');

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'INSUFFICIENT_FUNDS',
                'details' => ['available' => '10.0000', 'required' => '100.0000'],
            ],
        ])
        ->assertJsonStructure(['meta' => ['timestamp', 'request_id']]);
});

it('maps 404 routes to NOT_FOUND', function (): void {
    $this->getJson('/api/does-not-exist')
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND'],
        ]);
});

it('maps validation exceptions to VALIDATION_ERROR with a fields hash', function (): void {
    Route::middleware('api')->post('/api/_tests/validate', function (Illuminate\Http\Request $r): void {
        $r->validate(['email' => ['required', 'email']]);
    });

    $response = $this->postJson('/api/_tests/validate', ['email' => 'nope']);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => ['code' => 'VALIDATION_ERROR'],
        ])
        ->assertJsonPath('error.details.fields.email.0', fn ($m) => is_string($m));
});
