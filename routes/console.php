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
