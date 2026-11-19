<?php

use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\BoxLabelController;
use App\Http\Controllers\Web\OpsHeartbeatController;
use App\Http\Controllers\Web\PinController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\SpaController;
use App\Services\Admin\StaffView;
use App\Support\Geo\Geocoder;
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

// F71: back to the admin's own login after looking at the panel as a staff member.
Route::get('/admin-tools/staff-view/stop', fn () => StaffView::stop())->name('admin.staff-view.stop');

// F62: admins look up a home pin from an address or a map link (the panel's location box). F69: staff too.
Route::get('/admin-tools/geocode', function (Request $request, Geocoder $geocoder) {
    abort_unless($request->user()?->isTeam(), 403);
    $q = (string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:500']])['q'];

    return response()->json(['data' => $geocoder->search($q)]);
})->middleware('throttle:60,1')->name('admin.geocode');

// F78: the reply loops check in (admin's own browser); the status page shows it.
Route::post('/admin-tools/heartbeat', [OpsHeartbeatController::class, 'store'])
    ->middleware('throttle:120,1')->name('admin.heartbeat');

// F79: a shahidar's named box stickers, from a signed link in the app.
Route::get('/print/stickers/{batch}', BoxLabelController::class)->where('batch', '[a-z0-9-]+')->middleware('signed:relative')->name('stickers.print');

// F39: a customer's bill, from a link.
Route::get('/bill/{token}', [BillController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('bill.show');

// F68: a cook drops her home pin from a link.
Route::get('/pin/{token}', [PinController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('pin.show');
Route::post('/pin/{token}', [PinController::class, 'store'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:20,1')->name('pin.store');
Route::get('/pin/{token}/search', [PinController::class, 'search'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:20,1')->name('pin.search');

// F81: privacy policy (and how to delete your data), linked from the Meta app.
Route::get('/privacy', fn () => view('legal.privacy', ['meta' => [
    'title' => 'গোপনীয়তা নীতি · দাওয়াত', 'description' => 'দাওয়াত কোন তথ্য রাখে, কেন রাখে, আর কীভাবে মুছে দিতে বলবেন।',
    'url' => url('/privacy'), 'canonical' => url('/privacy'), 'image' => null,
]]))->name('privacy');

// Shared post links get Open Graph tags so chat apps show the share card.
Route::get('/p/{id}', [SpaController::class, 'post'])->name('spa.post');

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!(api|admin|livewire|filament|storage|build|up|pin|print)(/|$)).*$')
    ->name('spa');
