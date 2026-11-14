<?php

namespace App\Console\Commands;

use App\Services\Regular\TodayMenu;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('menus:announce')]
#[Description("From 8 am, tell regular customers what their cook's daily menu is today (F61)")]
class AnnounceDailyMenus extends Command
{
    public function handle(TodayMenu $menu): int
    {
        $n = $menu->runDailyMenus();
        if ($n) {
            $this->info("Announced {$n} menu(s).");
        }

        return self::SUCCESS;
    }
}
