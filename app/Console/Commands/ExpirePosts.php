<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:expire')]
#[Description('Mark posts whose "available until" time has passed as expired (F11)')]
class ExpirePosts extends Command
{
    public function handle(): int
    {
        // The feed already hides these by time; this keeps statuses honest for
        // the cook's list, the admin panel and reports.
        $count = Post::whereIn('status', [PostStatus::Live, PostStatus::SoldOut])
            ->where('available_until', '<=', now())
            ->update(['status' => PostStatus::Expired, 'updated_at' => now()]);

        $this->info("Expired {$count} post(s).");

        return self::SUCCESS;
    }
}
