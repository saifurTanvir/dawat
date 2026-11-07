<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, function () {
            return match (config('services.otp.driver')) {
                'log' => new LogOtpSender,
                default => throw new InvalidArgumentException('Unknown OTP_DRIVER: '.config('services.otp.driver')),
            };
        });
    }

    public function boot(): void
    {
        // Store UTC, show Dhaka time in the admin panel.
        FilamentTimezone::set(config('app.display_timezone'));

        // Per-IP cap on OTP endpoints, on top of the per-phone limits in OtpService.
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('feed', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        // Stops anyone harvesting cooks' numbers by tapping every post.
        RateLimiter::for('contact', fn (Request $request) => array_filter([
            Limit::perMinute(10)->by($request->ip()),
            Limit::perDay(60)->by($request->ip()),
            ($id = auth('sanctum')->id()) ? Limit::perDay(30)->by('user:'.$id) : null,
        ]));

        // F3: only approved cooks who finished kitchen setup may publish food.
        Gate::define('publish-posts', function (User $user): bool {
            $kitchen = $user->kitchen;

            return $user->isCook() && ! $user->isBlocked()
                && $kitchen !== null && $kitchen->isApproved() && $kitchen->isSetUp();
        });
    }
}
