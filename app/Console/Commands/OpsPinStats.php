<?php

namespace App\Console\Commands;

use App\Enums\KitchenStatus;
use App\Models\Kitchen;
use App\Models\MessengerThread;
use App\Models\PinLink;
use App\Support\DhakaTime;
use Illuminate\Console\Command;

/** Counts only (no names or numbers): how many cooks still have no home pin, and how many gave one today. */
class OpsPinStats extends Command
{
    protected $signature = 'ops:pin-stats';

    protected $description = 'Print pin progress counts';

    public function handle(): int
    {
        $today = DhakaTime::startOfDay();
        $approved = Kitchen::query()->where('status', KitchenStatus::Approved);
        $this->line('approved kitchens: '.(clone $approved)->count());
        $this->line('approved without pin: '.(clone $approved)->whereNull('location')->count());
        $this->line('pins given via link today: '.PinLink::query()->where('submitted_at', '>=', $today)->distinct('kitchen_id')->count('kitchen_id'));
        $this->line('pin links sent on Messenger today: '.MessengerThread::query()->get(['facts'])
            ->filter(fn ($t) => ($t->facts['pin_reminded_at'] ?? null) && $t->facts['pin_reminded_at'] >= $today->toIso8601String())->count());
        $this->line('messenger chats waiting now: '.MessengerThread::query()->waiting()->count());
        $this->line('plates unknown: '.(clone $approved)->whereNull('daily_plates')->count());

        return self::SUCCESS;
    }
}
