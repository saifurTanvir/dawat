<?php

namespace App\Listeners;

use App\Events\PostPublished;
use App\Jobs\GenerateShareCard;

class QueueShareCard
{
    public function handle(PostPublished $event): void
    {
        if (config('dawat.share_cards.enabled')) {
            // After commit: the post and its photos must be saved before the card reads them.
            GenerateShareCard::dispatch($event->post)->afterCommit();
        }
    }
}
