<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\SwapController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes — rate-limited to 5 requests per minute
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/2fa/verify', [AuthController::class, 'setupTwoFactor'])->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // 2FA management
    Route::prefix('2fa')->group(function () {
        Route::post('/setup', [AuthController::class, 'setupTwoFactor'])->name('2fa.setup');
        Route::post('/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('2fa.verify');
        Route::post('/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
    });

    // Wallets
    Route::get('/wallets', [WalletController::class, 'index']);

    // Ledger — read-only, auth only (no 2FA required for viewing)
    Route::get('/ledger/{wallet}', [LedgerController::class, 'index']);

    // High-value actions — require 2FA token ability
    Route::middleware(['require.2fa', 'throttle:10,1'])->group(function () {
        Route::post('/swap', [SwapController::class, 'swap']);
    });
});

/*
|--------------------------------------------------------------------------
| Webhooks — per-driver signature verification, rate-limited
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/webhooks/{driver}', [WebhookController::class, 'webhook']);
});
