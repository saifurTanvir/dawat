<?php

namespace App\Console\Commands;

use App\Services\Posts\PostForCook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:daily-menus')]
#[Description("Post cooks' daily menus whose time has come (F43)")]
class PostDailyMenus extends Command
{
    public function handle(PostForCook $posts): int
    {
        $n = $posts->runDailyMenus()->count();
        if ($n) {
            $this->info("Posted {$n} daily menu(s).");
        }

        return self::SUCCESS;
    }
}
