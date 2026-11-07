<?php

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
});
