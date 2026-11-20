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

        // Who can still be reached on Messenger: a chat linked to the kitchen (or carrying its
        // number) with a message from her in Meta's 7-day window.
        $pinless = (clone $approved)->whereNull('location')->with('user:id,phone')->get(['id', 'user_id']);
        $threads = MessengerThread::query()->get(['id', 'kitchen_id', 'facts', 'last_in_at', 'bot_off']);
        $byKitchen = $threads->whereNotNull('kitchen_id')->groupBy('kitchen_id');
        $byPhone = $threads->filter(fn ($t) => filled($t->facts['phone'] ?? null))->groupBy(fn ($t) => $t->facts['phone']);
        $window = now()->subHours(166);
        $open = $old = $none = $remindedRecently = 0;
        foreach ($pinless as $k) {
            $ts = ($byKitchen[$k->id] ?? collect())->merge($byPhone[$k->user?->phone] ?? collect());
            if ($ts->isEmpty()) {
                $none++;
            } elseif ($ts->contains(fn ($t) => ! $t->bot_off && $t->last_in_at && $t->last_in_at->gt($window))) {
                $open++;
                if ($ts->contains(fn ($t) => ($t->facts['pin_reminded_at'] ?? null) && $t->facts['pin_reminded_at'] >= now()->subHours(12)->toIso8601String())) {
                    $remindedRecently++;
                }
            } else {
                $old++;
            }
        }
        $this->line('no pin, reachable on Messenger now: '.$open.' (reminded in last 12h: '.$remindedRecently.')');
        $this->line('no pin, Messenger chat older than 7 days: '.$old);
        $this->line('no pin, no Messenger chat: '.$none);

        return self::SUCCESS;
    }
}
