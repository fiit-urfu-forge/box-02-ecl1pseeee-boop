<?php

declare(strict_types=1);

use App\Http\Controllers\Accounts\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Sbp\SbpController;
use App\Http\Controllers\Sbp\SbpWebhookController;
use App\Http\Controllers\Transfers\TransferController;
use App\Http\Controllers\User\ProfileController;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * ---------------------------------------------------------------------------
 * Public probe
 * ---------------------------------------------------------------------------
 */
Route::get('/ping', static function (Request $request) {
    return ApiResponse::success(
        ['pong' => true, 'service' => config('app.name')],
        request: $request,
    );
})->name('ping');

/*
 * ---------------------------------------------------------------------------
 * Authentication (§5.1)
 * ---------------------------------------------------------------------------
 */
Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth.register')
        ->name('auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth.login')
        ->name('auth.login');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    Route::post('/logout-all', [AuthController::class, 'logoutAll'])
        ->middleware('auth:sanctum')
        ->name('auth.logout-all');

    // Signed URL — no auth required, signature IS the auth.
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/resend', [VerifyEmailController::class, 'resend'])
        ->middleware(['auth:sanctum', 'throttle:6,1'])
        ->name('verification.resend');
});

/*
 * ---------------------------------------------------------------------------
 * Profile (§5.1, §4.3)
 * ---------------------------------------------------------------------------
 */
Route::prefix('user')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])->name('user.profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('user.profile.update');
    Route::post('/avatar', [ProfileController::class, 'uploadAvatar'])
        ->middleware('throttle:avatar')
        ->name('user.avatar.upload');
    Route::get('/avatar', [ProfileController::class, 'showAvatar'])->name('user.avatar');
});

/*
 * ---------------------------------------------------------------------------
 * Accounts (§5.2)
 * ---------------------------------------------------------------------------
 */
Route::prefix('accounts')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/', [AccountController::class, 'store'])
        ->middleware('idempotency')
        ->name('accounts.store');
    Route::get('/{id}', [AccountController::class, 'show'])
        ->whereUuid('id')
        ->name('accounts.show');
});

/*
 * ---------------------------------------------------------------------------
 * Transfers (§5.3)
 * ---------------------------------------------------------------------------
 */
Route::prefix('transfers')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('/', [TransferController::class, 'store'])
        ->middleware(['idempotency', 'throttle:transfers'])
        ->name('transfers.store');
    Route::get('/{id}', [TransferController::class, 'show'])
        ->whereUuid('id')
        ->name('transfers.show');
});

/*
 * ---------------------------------------------------------------------------
 * SBP (§5.4) — MVP stub
 * ---------------------------------------------------------------------------
 */
Route::prefix('sbp')->middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/link-phone', [SbpController::class, 'linkPhone'])
        ->middleware('idempotency')
        ->name('sbp.link-phone');
    Route::post('/transfer', [SbpController::class, 'transfer'])
        ->middleware(['idempotency', 'throttle:transfers'])
        ->name('sbp.transfer');
});

// Webhook — NO auth middleware, signature IS the auth.
Route::post('/webhooks/sbp', [SbpWebhookController::class, 'handle'])->name('sbp.webhook');
