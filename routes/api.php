<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Every client (the PWA today, a native app later) talks to these routes.
| Breaking changes go to a new /api/v2 group instead of changing v1.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/ping', fn () => ['ok' => true, 'app' => config('app.name')])->name('ping');

    // F1: phone + OTP sign-in
    Route::middleware('throttle:otp')->prefix('auth')->name('auth.')->group(function () {
        Route::post('/otp', [OtpController::class, 'request'])->name('otp');
        Route::post('/verify', [OtpController::class, 'verify'])->name('verify');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [OtpController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [MeController::class, 'show'])->name('me');
        Route::patch('/me', [MeController::class, 'update'])->name('me.update');

        // F3: become a cook (pending until an admin approves)
        Route::get('/kitchen', [KitchenController::class, 'show'])->name('kitchen.show');
        Route::post('/kitchen', [KitchenController::class, 'store'])->name('kitchen.store');
    });
});
