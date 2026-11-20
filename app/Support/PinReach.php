<?php

namespace App\Support;

use App\Models\Kitchen;
use App\Models\MessengerThread;
use Illuminate\Support\Collection;

/**
 * Can a cook still be reached on Messenger? A chat linked to her kitchen (or carrying
 * her number) with a message from her inside Meta's 7-day window = "open"; a chat
 * but older = "old"; no chat at all = "none" (she came by WhatsApp, a list or the panel).
 */
final class PinReach
{
    public const LABELS = ['open' => 'Messenger-এ পাওয়া যায়', 'old' => 'Messenger পুরনো (৭ দিনের বেশি)', 'none' => 'Messenger নেই'];

    /**
     * @param  Collection<int, Kitchen>  $kitchens  with user loaded
     * @return array<int, array{reach: string, reminded: bool}> kitchen id =>
     */
    public static function of(Collection $kitchens): array
    {
        $threads = MessengerThread::query()->get(['id', 'kitchen_id', 'facts', 'last_in_at', 'bot_off']);
        $byKitchen = $threads->whereNotNull('kitchen_id')->groupBy('kitchen_id');
        $byPhone = $threads->filter(fn ($t) => filled($t->facts['phone'] ?? null))->groupBy(fn ($t) => $t->facts['phone']);
        $window = now()->subHours(166);
        $recent = now()->subHours(12)->toIso8601String();

        return $kitchens->mapWithKeys(function (Kitchen $k) use ($byKitchen, $byPhone, $window, $recent) {
            $ts = ($byKitchen[$k->id] ?? collect())->merge($byPhone[$k->user?->phone] ?? collect());
            $reach = match (true) {
                $ts->isEmpty() => 'none',
                $ts->contains(fn ($t) => ! $t->bot_off && $t->last_in_at && $t->last_in_at->gt($window)) => 'open',
                default => 'old',
            };

            return [$k->id => ['reach' => $reach, 'reminded' => $ts->contains(fn ($t) => ($t->facts['pin_reminded_at'] ?? null) >= $recent)]];
        })->all();
    }
}
