<?php

namespace App\Console\Commands;

use App\Models\MessengerThread;
use App\Models\OpsHeartbeat;
use App\Services\Messenger\Brain;
use App\Services\Messenger\Graph;
use Illuminate\Console\Command;

/**
 * F80: while the server answers Messenger, tells the status page (F78) it is
 * alive even when nobody writes, and raises a problem when chats are left
 * unanswered or sending fails.
 */
class MessengerHeartbeat extends Command
{
    protected $signature = 'messenger:heartbeat';

    protected $description = 'Report the Messenger bot\'s state to the status page';

    public function handle(): int
    {
        if (! Graph::botOn()) {
            return self::SUCCESS;
        }
        $pending = MessengerThread::query()->waiting()->where('bot_off', false)
            ->where(fn ($q) => $q->whereNull('paused_until')->orWhere('paused_until', '<', now()))->count();
        $failing = MessengerThread::query()->whereNotNull('send_error')->where('updated_at', '>', now()->subHour())->first();
        $note = match (true) {
            ! Graph::configured() => 'Messenger-এর টোকেন বা app secret সার্ভারে নেই',
            ! Brain::available() => 'AI-এর key নেই — বট শুধু প্রথম মেসেজ দিচ্ছে',
            $failing !== null => 'পাঠানো যাচ্ছে না: '.$failing->send_error,
            $pending > 0 => "{$pending}টা চ্যাট উত্তরের অপেক্ষায়",
            default => 'সার্ভার (Messenger API) চালু',
        };
        OpsHeartbeat::create([
            'channel' => 'messenger', 'sent' => 0, 'pending' => $pending, 'note' => mb_substr($note, 0, 255),
            'problem' => ! Graph::configured() || ! Brain::available() || $failing !== null || $pending > 0,
        ]);

        return self::SUCCESS;
    }
}
