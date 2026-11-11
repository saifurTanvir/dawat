<?php

use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\SpaController;
use App\Support\QrImage;
use Illuminate\Http\Request;
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

// F36: QR for the install page, to scan from another phone.
Route::get('/app/qr.svg', fn () => response(QrImage::svg(url('/app')), 200, [
    'Content-Type' => 'image/svg+xml',
    'Cache-Control' => 'public, max-age=86400',
]))->name('app.qr');

// F50: the admin shows a one-time login link as a QR on their own screen.
Route::get('/admin-tools/login-qr', function (Request $request) {
    abort_unless($request->user()?->hasRole('admin'), 403);
    $url = (string) $request->query('u');
    abort_unless(str_starts_with($url, url('/l/')), 404);

    return response()->view('admin.login-qr', ['url' => $url, 'svg' => QrImage::svg($url)])->header('Cache-Control', 'no-store');
})->name('admin.login-qr');

// F39: a customer's bill, from a link.
Route::get('/bill/{token}', [BillController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('bill.show');

// Shared post links get Open Graph tags so chat apps show the share card.
Route::get('/p/{id}', [SpaController::class, 'post'])->name('spa.post');

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!(api|admin|livewire|filament|storage|build|up)(/|$)).*$')
    ->name('spa');
