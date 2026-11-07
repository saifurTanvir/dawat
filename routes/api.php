<?php

use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\My\MyPostController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\TagController;
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
    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');

    // F12–F14: what buyers see. Public, so a QR scan works without signing in.
    Route::get('/feed', [FeedController::class, 'index'])->middleware('throttle:feed')->name('feed');
    // F15: printed QR codes
    Route::get('/qr/{code}', [QrController::class, 'show'])->middleware('throttle:feed')->name('qr.show');

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
        // F4–F5: location, area and contact preferences
        Route::patch('/kitchen', [KitchenController::class, 'update'])->name('kitchen.update');

        // F6–F9: publish a post
        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

        // F10: the cook's own posts
        Route::get('/my/posts', [MyPostController::class, 'index'])->name('my.posts.index');
        Route::patch('/my/posts/{post}', [MyPostController::class, 'update'])->name('my.posts.update');
        Route::post('/my/posts/{post}/sold-out', [MyPostController::class, 'soldOut'])->name('my.posts.sold-out');
    });
});
