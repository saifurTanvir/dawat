<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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
        if (config('services.otp.any_code') && ! $this->app->environment('local')) {
            // Has no effect outside local, but someone left it on: say so.
            logger()->warning('OTP_ANY_CODE is set but ignored because APP_ENV is not "local". Remove it from this .env.');
        }

        // Read from config (not env) so it still works after `php artisan optimize`.
        if ($proxies = config('dawat.trusted_proxies')) {
            TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        }

        // Store UTC, show Dhaka time in the admin panel.
        FilamentTimezone::set(config('app.display_timezone'));

        // Per-IP cap on OTP endpoints, on top of the per-phone limits in OtpService.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perDay(config('dawat.otp.per_ip_per_day'))->by('day:'.$request->ip()),
        ]);
        // Only sending a code costs money, so the app-wide cap applies to that route alone.
        RateLimiter::for('otp-send', fn () => Limit::perHour(config('dawat.otp.global_per_hour'))->by('otp-global')
            ->response(fn () => response()->json([
                'message' => 'এখন অনেকে একসাথে কোড চাইছেন। কয়েক মিনিট পরে আবার চেষ্টা করুন।',
            ], 429)));
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
