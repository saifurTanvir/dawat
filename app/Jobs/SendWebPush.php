<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/** F37: delivers one notification to every device of a user; forgets devices that unsubscribed. */
class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public string $url = '/',
        public ?string $tag = null,
    ) {}

    public function handle(): void
    {
        $subs = PushSubscription::where('user_id', $this->userId)->get();
        if ($subs->isEmpty()) {
            return;
        }

        $push = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 6 * 3600, 'urgency' => 'high']);

        $payload = json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subs as $sub) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
            ]), $payload);
        }

        try {
            foreach ($push->flush() as $report) {
                $endpoint = $report->getEndpoint();
                if ($report->isSuccess()) {
                    PushSubscription::where('endpoint', $endpoint)->update(['last_used_at' => now()]);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                } else {
                    Log::info('web push failed', ['reason' => $report->getReason()]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('web push error', ['error' => $e->getMessage()]);
        }
    }
}
