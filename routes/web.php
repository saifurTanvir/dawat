<?php

use Illuminate\Support\Facades\Route;

/*
| The Vue PWA handles every path that is not the API, the admin panel or a
| framework route. Vue Router decides what to show.
*/
Route::view('/{any?}', 'spa')
    ->where('any', '^(?!(api|admin|livewire|filament|storage|build|up)(/|$)).*$')
    ->name('spa');
