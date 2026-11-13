<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** F54: send one event to Meta's Conversions API. A failure is logged, never shown to the person. */
class SendMetaEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /** @param  array<string, mixed>  $event */
    public function __construct(public array $event) {}

    public function handle(): void
    {
        $pixel = config('services.meta.pixel_id');
        $token = config('services.meta.capi_token');

        if (blank($pixel) || blank($token)) {
            return;
        }

        $body = array_filter([
            'data' => [$this->event],
            'test_event_code' => config('services.meta.test_event_code'),
        ]);

        $response = Http::timeout(15)
            ->post(sprintf('https://graph.facebook.com/%s/%s/events?access_token=%s', config('services.meta.graph_version'), $pixel, urlencode($token)), $body);

        if ($response->failed()) {
            Log::warning('Meta Conversions API refused an event', [
                'event' => $this->event['event_name'] ?? null,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            if ($response->serverError()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 300);
            }
        }
    }
}
