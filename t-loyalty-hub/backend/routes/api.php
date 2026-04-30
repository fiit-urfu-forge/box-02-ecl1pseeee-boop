<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\OffersController;
use App\Http\Controllers\Api\GamificationController;

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}', [UserController::class, 'show']);

Route::prefix('loyalty')->group(function () {
    Route::get('/summary', [LoyaltyController::class, 'summary']);
    Route::get('/history', [LoyaltyController::class, 'history']);
    Route::get('/programs', [LoyaltyController::class, 'programs']);
});

Route::get('/offers', [OffersController::class, 'index']);

Route::prefix('gamification')->group(function () {
    Route::get('/{userId}', [GamificationController::class, 'show']);
    Route::post('/{userId}/visit', [GamificationController::class, 'recordVisit']);
});

Route::prefix('ai')->group(function () {
    Route::get('/shadow-portfolio/{userId}', [LoyaltyController::class, 'shadowPortfolio']);
    Route::get('/nudging/{userId}', [LoyaltyController::class, 'dynamicNudging']);
    Route::get('/cross-sell/{userId}', [OffersController::class, 'crossSellOptimizer']);
    Route::get('/zero-click/{userId}', [OffersController::class, 'zeroClick']);
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));
