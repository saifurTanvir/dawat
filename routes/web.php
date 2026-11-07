<?php

use App\Http\Controllers\Web\SpaController;
use Illuminate\Support\Facades\Route;

/*
| The Vue PWA handles every path that is not the API, the admin panel or a
| framework route. Vue Router decides what to show.
*/

// Shared post links get Open Graph tags so chat apps show the share card.
Route::get('/p/{id}', [SpaController::class, 'post'])->name('spa.post');

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!(api|admin|livewire|filament|storage|build|up)(/|$)).*$')
    ->name('spa');
