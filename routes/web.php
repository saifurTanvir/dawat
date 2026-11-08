<?php

use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\SpaController;
use Illuminate\Support\Facades\Route;

/*
| The Vue PWA handles every path that is not the API, the admin panel or a
| framework route. Vue Router decides what to show.
*/

// F31: pages for search engines and people arriving from Google.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/area/{slug}', [SeoController::class, 'area'])->where('slug', '[a-z0-9-]+')->name('seo.area');
Route::get('/lunch', [SeoController::class, 'lunch'])->name('seo.lunch');
Route::get('/rannaghor', [SeoController::class, 'rannaghor'])->name('seo.rannaghor');
Route::get('/about', [SeoController::class, 'about'])->name('seo.about');

// Shared post links get Open Graph tags so chat apps show the share card.
Route::get('/p/{id}', [SpaController::class, 'post'])->name('spa.post');

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!(api|admin|livewire|filament|storage|build|up)(/|$)).*$')
    ->name('spa');
