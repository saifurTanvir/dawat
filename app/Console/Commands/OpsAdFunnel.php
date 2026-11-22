<?php

namespace App\Console\Commands;

use App\Models\MessengerThread;
use App\Services\Ads\BaddaAds;
use App\Support\DhakaTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * F98: how the chats from the Badda ads went, message by message, to see where people stop.
 * No names; numbers in the person's text are masked.
 */
class OpsAdFunnel extends Command
{
    protected $signature = 'ops:ad-funnel {ad=customer : cook|customer|manager|all}';

    protected $description = 'Print the message flow and drop-off of the Badda ad chats';

    public function handle(BaddaAds $ads): int
    {
        $byAd = $ads->threadsByAd();
        $keys = $this->argument('ad') === 'all' ? array_keys($byAd) : [$this->argument('ad')];

        foreach ($keys as $key) {
            /** @var list<MessengerThread> $threads */
            $threads = $byAd[$key] ?? [];
            $this->line('');
            $this->line("===== {$key}: ".count($threads).' chats =====');
            $taps = [];
            $dropAfter = [];
            $personMsgs = [];
            $hours = [];
            $replyGaps = [];

            foreach ($threads as $i => $t) {
                $msgs = $t->messages->sortBy('id')->values();
                $facts = $t->facts ?? [];
                $in = $msgs->where('direction', 'in');
                $personMsgs[] = $in->count();
                if ($first = $in->first()) {
                    $hours[] = Carbon::parse($first->created_at)->timezone(DhakaTime::TZ)->format('H');
                }
                foreach ($in as $m) {
                    if (in_array(trim((string) $m->text), BaddaAds::ADS[$key]['questions'] ?? [], true)) {
                        $taps[trim((string) $m->text)] = ($taps[trim((string) $m->text)] ?? 0) + 1;
                    }
                }
                // Bot reply speed: each person message → next out message.
                foreach ($msgs as $j => $m) {
                    if ($m->direction === 'in') {
                        $next = $msgs->slice($j + 1)->firstWhere('direction', 'out');
                        if ($next) {
                            $replyGaps[] = Carbon::parse($m->created_at)->diffInSeconds(Carbon::parse($next->created_at), true);
                        }
                    }
                }
                $last = $msgs->last();
                $lastOut = $msgs->where('direction', 'out')->last();
                $stoppedAfterUs = $last && $last->direction === 'out';
                if ($stoppedAfterUs && $lastOut) {
                    $dropAfter[] = Str::limit(preg_replace('/\s+/u', ' ', (string) $lastOut->text), 70);
                }

                $this->line('');
                $this->line(sprintf('#%d  first %s · person msgs %d · our msgs %d · gave: %s%s%s · %s',
                    $i + 1,
                    $first ? Carbon::parse($first->created_at)->timezone(DhakaTime::TZ)->format('d M H:i') : '—',
                    $in->count(),
                    $msgs->where('direction', 'out')->count(),
                    filled($facts['phone'] ?? null) ? 'phone ' : '',
                    filled($facts['customer_address'] ?? null) || filled($facts['area_text'] ?? null) ? 'area/address ' : '',
                    filled($facts['name'] ?? null) || filled($t->name) ? 'name' : '',
                    $stoppedAfterUs ? 'STOPPED after our reply' : 'last word: person'.($t->needs_human ? ' (needs human)' : ''),
                ));
                foreach ($msgs as $m) {
                    $who = $m->direction === 'in' ? 'P' : match ($m->by) {
                        'bot' => 'B', 'admin' => 'A', default => 'G'
                    };
                    $text = preg_replace('/\d{5,}/', '#####', preg_replace('/\s+/u', ' ', (string) $m->text));
                    $text = preg_replace('#https://dawat\.cloud/pin/\S+#', '[pin]', $text);
                    $this->line('   '.$who.' '.Carbon::parse($m->created_at)->timezone(DhakaTime::TZ)->format('H:i').'  '.Str::limit($text, $who === 'P' ? 90 : 140));
                }
            }

            $this->line('');
            $this->line("--- {$key} summary ---");
            $this->line('quick questions tapped: '.json_encode($taps, JSON_UNESCAPED_UNICODE));
            $this->line('person messages per chat: '.json_encode(array_count_values(array_map('strval', $personMsgs))));
            $this->line('first-message hour: '.json_encode(array_count_values($hours)));
            $this->line('stopped after our reply: '.count($dropAfter).' of '.count($threads));
            foreach (array_count_values($dropAfter) as $txt => $n) {
                $this->line("   {$n}× after: {$txt}");
            }
            if ($replyGaps) {
                sort($replyGaps);
                $this->line('our reply time (s): median '.$replyGaps[intdiv(count($replyGaps), 2)].', max '.max($replyGaps));
            }
        }

        return self::SUCCESS;
    }
}
