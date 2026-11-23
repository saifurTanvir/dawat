<?php

use App\Models\AppNotification;
use App\Models\OtpCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete OTP codes older than a day.
Schedule::command('model:prune', ['--model' => [OtpCode::class, AppNotification::class]])->daily();

// F11: close posts whose time is up.
Schedule::command('posts:expire')->everyFiveMinutes()->withoutOverlapping();

// F30: boxes held for a price answer that never came go back to the feed.
Schedule::command('orders:expire-asks')->everyMinute()->withoutOverlapping();

// F39: weekly bills on Saturday, monthly on the 1st, early morning in Dhaka.
Schedule::command('bills:issue')->dailyAt('07:10')->timezone('Asia/Dhaka')->withoutOverlapping();

// F43: cooks' daily menus post themselves at their time.
Schedule::command('posts:daily-menus')->everyMinute()->withoutOverlapping();
// F61: from 8 am, daily menus tell regular customers what is cooking today.
Schedule::command('menus:announce')->everyFiveMinutes()->withoutOverlapping();

// F95: on office rounds, cooks hear about 30 minutes before the area manager comes.
Schedule::command('rounds:tick')->everyMinute()->withoutOverlapping();

// F64: the cook has 5 minutes to answer a guest-meal request.
Schedule::command('guests:follow-up')->everyMinute()->withoutOverlapping();

// F80: the Messenger bot checks in on the status page while it is on.
Schedule::command('messenger:heartbeat')->everyTenMinutes()->withoutOverlapping();

// Cooks without a home pin get their own link on Messenger during the day.
Schedule::command('messenger:customer-followups')->dailyAt('10:30')->timezone('Asia/Dhaka')->withoutOverlapping(); // F100
Schedule::command('messenger:remind-pins')->hourlyAt(5)->between('7:00', '21:00')->timezone('Asia/Dhaka')->withoutOverlapping();
// Chats whose answer did not go out (e.g. the AI timed out) are tried again.
Schedule::command('messenger:answer-waiting')->everyFiveMinutes()->withoutOverlapping();

// F80: when many chats come at once, three more queue workers answer alongside the main one.
// Each lives just under a minute and the scheduler starts it again, so nothing on the server changes.
foreach ([1, 2, 3] as $i) {
    Schedule::command("queue:work redis --queue=default --name=messenger-{$i} --sleep=1 --tries=4 --timeout=85 --max-time=55")
        ->everyMinute()->withoutOverlapping(3)->runInBackground();
}
