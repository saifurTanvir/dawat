<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\HistoryController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\ImpersonationController;
use App\Http\Controllers\Api\V1\KitchenContactController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\My\MyBookingController;
use App\Http\Controllers\Api\V1\My\MyMenuController;
use App\Http\Controllers\Api\V1\My\MyPostController;
use App\Http\Controllers\Api\V1\My\MySalesController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostShowController;
use App\Http\Controllers\Api\V1\PushController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\RegularController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ShahidarApplicationController;
use App\Http\Controllers\Api\V1\ShahidarController;
use App\Http\Controllers\Api\V1\ShahidarKitchenController;
use App\Http\Controllers\Api\V1\StickerController;
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
    Route::get('/push/key', [PushController::class, 'key'])->name('push.key');

    // F12–F14: what buyers see. Public, so a QR scan works without signing in.
    // F53: apply to work as a shahidar (public)
    Route::get('/shahidar-applications/options', [ShahidarApplicationController::class, 'options'])->middleware('throttle:feed')->name('shahidar-applications.options');
    Route::post('/shahidar-applications', [ShahidarApplicationController::class, 'store'])->middleware('throttle:apply')->name('shahidar-applications.store');

    // F52: the first screen for new visitors
    Route::get('/home', HomeController::class)->middleware('throttle:feed')->name('home');
    Route::get('/feed', [FeedController::class, 'index'])->middleware('throttle:feed')->name('feed');
    // F15: printed QR codes
    Route::get('/qr/{code}', [QrController::class, 'show'])->middleware('throttle:feed')->name('qr.show');
    // F16, F18: call / message / share — guests too; reveals the number only on tap
    Route::post('/posts/{post}/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('posts.contact');
    // F17: one post (shared links) and its comments
    Route::get('/posts/{post}', PostShowController::class)->middleware('throttle:feed')->name('posts.show');
    Route::get('/posts/{post}/comments', [CommentController::class, 'index'])->middleware('throttle:feed')->name('posts.comments.index');

    // F27: scanning a box sticker (sign-in optional; strangers see nothing personal)
    Route::get('/stickers/{code}', [StickerController::class, 'show'])->middleware('throttle:feed')->name('stickers.show');

    // F1: phone + OTP sign-in
    Route::middleware('throttle:otp')->prefix('auth')->name('auth.')->group(function () {
        Route::post('/otp', [OtpController::class, 'request'])->middleware('throttle:otp-send')->name('otp');
        Route::post('/verify', [OtpController::class, 'verify'])->name('verify');
        // F50: when the SMS does not arrive
        Route::post('/link', [OtpController::class, 'link'])->name('link');
        Route::post('/no-code', [OtpController::class, 'noCode'])->name('no-code');
    });

    // F51: opening the app as someone else (the code comes from the admin or a shahidar)
    Route::post('/impersonate/claim', [ImpersonationController::class, 'claim'])->middleware('throttle:otp')->name('impersonate.claim');

    Route::middleware(['auth:sanctum', 'active', 'impersonation'])->group(function () {
        Route::post('/impersonate/end', [ImpersonationController::class, 'end'])->name('impersonate.end');
        Route::post('/auth/logout', [OtpController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [MeController::class, 'show'])->name('me');
        Route::post('/push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:comments')->name('push.subscribe');
        Route::delete('/push/subscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
        Route::patch('/me', [MeController::class, 'update'])->name('me.update');
        Route::put('/me/contact-hours', [MeController::class, 'contactHours'])->name('me.contact-hours');

        // F3: become a cook (pending until an admin approves)
        Route::get('/kitchen', [KitchenController::class, 'show'])->name('kitchen.show');
        Route::post('/kitchen', [KitchenController::class, 'store'])->name('kitchen.store');
        // F4–F5: location, area and contact preferences
        Route::patch('/kitchen', [KitchenController::class, 'update'])->name('kitchen.update');
        // F5: another contact number, confirmed by OTP or by an admin
        Route::post('/kitchen/contact', [KitchenContactController::class, 'store'])->middleware(['throttle:otp', 'throttle:otp-send'])->name('kitchen.contact.store');
        Route::post('/kitchen/contact/verify', [KitchenContactController::class, 'verify'])->middleware('throttle:otp')->name('kitchen.contact.verify');
        Route::delete('/kitchen/contact', [KitchenContactController::class, 'destroy'])->name('kitchen.contact.destroy');

        // F6–F9: publish a post
        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

        // F20: AI suggestions while writing a post (approved cooks only)
        Route::middleware(['can:publish-posts', 'throttle:ai'])->prefix('ai')->name('ai.')->group(function () {
            Route::post('/photo', [AiController::class, 'photo'])->name('photo');
            Route::post('/note', [AiController::class, 'note'])->name('note');
        });

        // F10: the cook's own posts
        Route::get('/my/posts', [MyPostController::class, 'index'])->name('my.posts.index');
        Route::patch('/my/posts/{post}', [MyPostController::class, 'update'])->name('my.posts.update');
        Route::post('/my/posts/{post}/sold-out', [MyPostController::class, 'soldOut'])->name('my.posts.sold-out');

        // F29: "নিতে চাই" — one-off orders from the feed
        Route::post('/posts/{post}/orders', [OrderController::class, 'store'])->middleware('throttle:comments')->name('posts.orders.store');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        // F26: the cook's regular bookings today
        Route::get('/my/bookings', [MyBookingController::class, 'index'])->name('my.bookings.index');
        Route::post('/my/bookings/ready', [MyBookingController::class, 'ready'])->middleware('can:publish-posts')->name('my.bookings.ready');
        Route::post('/my/bookings/off', [MyBookingController::class, 'off'])->middleware('can:publish-posts')->name('my.bookings.off');
        // F43: daily menus that post themselves
        Route::get('/my/menus', [MyMenuController::class, 'index'])->name('my.menus.index');
        // F47: sales, reviews and history
        Route::get('/my/sales', [MySalesController::class, 'index'])->name('my.sales');
        Route::post('/my/reviews/{review}/reply', [MySalesController::class, 'reply'])->middleware('throttle:comments')->name('my.reviews.reply');
        Route::get('/my/history', [HistoryController::class, 'index'])->name('my.history');
        Route::post('/my/history/{order}/review', [HistoryController::class, 'review'])->middleware('throttle:comments')->name('my.history.review');
        // F44: before posting, where the plates will go
        Route::get('/my/posts/preview', [PostController::class, 'preview'])->name('my.posts.preview');
        Route::put('/my/menus/{slot}', [MyMenuController::class, 'save'])->middleware('can:publish-posts')->name('my.menus.save');
        Route::delete('/my/menus/{slot}', [MyMenuController::class, 'destroy'])->name('my.menus.destroy');
        // F29: the cook's one-off orders
        Route::post('/my/orders/{order}/decline', [MyBookingController::class, 'decline'])->name('my.orders.decline');
        Route::post('/my/orders/{order}/handed', [MyBookingController::class, 'handed'])->name('my.orders.handed');

        // F27: the shahidar's day
        Route::middleware('can:work-as-shahidar')->prefix('shahidar')->name('shahidar.')->group(function () {
            Route::get('/today', [ShahidarController::class, 'today'])->name('today');
            Route::post('/orders/{order}/pickup', [ShahidarController::class, 'pickup'])->name('orders.pickup');
            Route::post('/orders/{order}/deliver', [ShahidarController::class, 'deliver'])->middleware('throttle:otp')->name('orders.deliver');
            Route::post('/orders/{order}/fail', [ShahidarController::class, 'fail'])->name('orders.fail');
            Route::post('/orders/{order}/paid', [ShahidarController::class, 'paid'])->name('orders.paid');
            Route::post('/orders/{order}/skip', [ShahidarController::class, 'skip'])->name('orders.skip');
            Route::post('/orders/{order}/accept', [ShahidarController::class, 'accept'])->name('orders.accept');
            // F43: post today's food for a cook after a phone call
            Route::post('/kitchens/{kitchen}/posts', [ShahidarController::class, 'postFor'])->middleware('throttle:comments')->name('kitchens.posts');
            Route::get('/kitchens/{kitchen}/preview', [ShahidarController::class, 'preview'])->name('kitchens.preview');
            // F46: act for a cook
            Route::get('/kitchens/{kitchen}', [ShahidarKitchenController::class, 'show'])->name('kitchens.show');
            Route::post('/kitchens/{kitchen}/view-as', [ImpersonationController::class, 'shahidarStart'])->name('kitchens.view-as');
            Route::post('/kitchens/{kitchen}/ready', [ShahidarKitchenController::class, 'ready'])->name('kitchens.ready');
            Route::post('/kitchens/{kitchen}/off', [ShahidarKitchenController::class, 'off'])->name('kitchens.off');
            Route::put('/kitchens/{kitchen}/menus/{slot}', [ShahidarKitchenController::class, 'saveMenu'])->name('kitchens.menus.save');
            Route::delete('/kitchens/{kitchen}/menus/{slot}', [ShahidarKitchenController::class, 'deleteMenu'])->name('kitchens.menus.destroy');
            Route::patch('/posts/{post}', [ShahidarKitchenController::class, 'updatePost'])->name('posts.update');
            Route::post('/posts/{post}/sold-out', [ShahidarKitchenController::class, 'soldOut'])->name('posts.sold-out');
            Route::post('/kitchen-orders/{order}/decline', [ShahidarKitchenController::class, 'decline'])->name('kitchen-orders.decline');
            Route::post('/kitchen-orders/{order}/handed', [ShahidarKitchenController::class, 'handed'])->name('kitchen-orders.handed');
            Route::post('/reviews/{review}/reply', [ShahidarKitchenController::class, 'reply'])->middleware('throttle:comments')->name('reviews.reply');
        });
        Route::post('/stickers/{code}/received', [StickerController::class, 'received'])->name('stickers.received');

        // F25: regular home food (customer side)
        Route::get('/regular', [RegularController::class, 'show'])->name('regular.show');
        Route::get('/regular/bills', [RegularController::class, 'bills'])->name('regular.bills');
        Route::put('/regular', [RegularController::class, 'save'])->middleware('throttle:comments')->name('regular.save');
        // F26: skip today's box, days off
        Route::post('/regular/orders/{order}/skip', [RegularController::class, 'skip'])->name('regular.orders.skip');
        Route::post('/regular/orders/{order}/accept', [RegularController::class, 'accept'])->name('regular.orders.accept');
        Route::post('/regular/pauses', [RegularController::class, 'pause'])->middleware('throttle:comments')->name('regular.pauses.store');
        Route::delete('/regular/pauses/{pause}', [RegularController::class, 'unpause'])->name('regular.pauses.destroy');

        // F17: comments and reports
        Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:comments')->name('posts.comments.store');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:comments')->name('reports.store');
    });
});
