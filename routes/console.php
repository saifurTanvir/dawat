<?php

use App\Models\OtpCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete OTP codes older than a day.
Schedule::command('model:prune', ['--model' => [OtpCode::class]])->daily();

// F11: close posts whose time is up.
Schedule::command('posts:expire')->everyFiveMinutes()->withoutOverlapping();

// F30: boxes held for a price answer that never came go back to the feed.
Schedule::command('orders:expire-asks')->everyMinute()->withoutOverlapping();

// F39: weekly bills on Saturday, monthly on the 1st, early morning in Dhaka.
Schedule::command('bills:issue')->dailyAt('07:10')->timezone('Asia/Dhaka')->withoutOverlapping();
