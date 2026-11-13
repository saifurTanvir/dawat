<?php

namespace App\Services\Meta;

use App\Jobs\SendMetaEvent;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

/**
 * F54: Facebook (Meta) ad tracking.
 *
 * One event is reported twice: by the browser pixel and by the server
 * (Conversions API). Both carry the same event id, so Meta counts it once
 * and still sees it when an ad blocker stops the pixel.
 *
 * Nothing happens unless META_PIXEL_ID is set.
 */
class MetaEvents
{
    public function enabled(): bool
    {
        return filled(config('services.meta.pixel_id'));
    }

    public function serverEnabled(): bool
    {
        return $this->enabled() && filled(config('services.meta.capi_token'));
    }

    /**
     * Report one event and return what the browser pixel needs, for the API response
     * as `meta_event` (resources/js/lib/api.js fires it). Null when tracking is off.
     *
     * @param  array<string, scalar>  $custom  e.g. ['value' => 120, 'currency' => 'BDT']
     * @return array{name: string, id: string, params: array<string, scalar>}|null
     */
    public function record(string $name, string $eventId, Request $request, ?User $user = null, ?string $phone = null, array $custom = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        if ($this->serverEnabled()) {
            SendMetaEvent::dispatch([
                'event_name' => $name,
                'event_time' => now()->getTimestamp(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $request->headers->get('referer') ?: config('app.url'),
                'user_data' => $this->userData($request, $user, $phone),
                'custom_data' => (object) $custom,
            ]);
        }

        return ['name' => $name, 'id' => $eventId, 'params' => $custom];
    }

    /**
     * Who did it, in the hashed form Meta asks for. The phone number never leaves unhashed.
     *
     * @return array<string, mixed>
     */
    public function userData(Request $request, ?User $user, ?string $phone): array
    {
        $phone = PhoneNumber::normalize($phone ?? $user?->phone);

        return array_filter([
            'ph' => $phone ? [hash('sha256', PhoneNumber::international($phone))] : null,
            'external_id' => $user ? [hash('sha256', 'dawat-user-'.$user->id)] : null,
            'country' => [hash('sha256', 'bd')],
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbp' => $request->cookie('_fbp'),
            'fbc' => $request->cookie('_fbc'),
        ]);
    }
}
