<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Admin\QrCodeController;
use App\Http\Controllers\Admin\StickerSheetController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // F58: microphone buttons for Bangla voice typing in the quick-add forms.
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.voice-input'))
            ->login()
            ->brandName('দাওয়াত অ্যাডমিন')
            ->brandLogo(asset('brand/wordmark.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('icons/favicon-48.png'))
            ->colors([
                'primary' => Color::hex('#1e5b3f'),
                'warning' => Color::hex('#e8a90c'),
                'danger' => Color::hex('#b8322a'),
            ])
            ->font('Noto Sans Bengali')
            ->navigationGroups(['রাঁধুনি ও খাবার', 'নিয়মিত খাবার', 'নিরাপত্তা', 'সেটিংস'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // F23 widgets are discovered from app/Filament/Widgets.
            ->widgets([])
            ->authenticatedRoutes(function () {
                Route::get('qr/{qr}/poster', [QrCodeController::class, 'poster'])->name('qr.poster');
                Route::get('qr/{qr}/png', [QrCodeController::class, 'png'])->name('qr.png');
                Route::get('stickers/sheet/{batch}', StickerSheetController::class)->name('stickers.sheet');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
