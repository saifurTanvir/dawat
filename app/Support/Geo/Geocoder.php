<?php

namespace App\Support\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F62: turns what someone types or pastes into a point.
 *
 * - "23.78, 90.42", a Google Maps / WhatsApp location link (MapLink)
 * - a short share link (maps.app.goo.gl, goo.gl/maps): followed to the full link
 * - an address: searched with Google (GOOGLE_MAPS_KEY) or, without a key,
 *   OpenStreetMap — in Bangladesh, nearest Dhaka first.
 */
class Geocoder
{
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.co', 'maps.google.com', 'www.google.com', 'google.com'];

    /** A point from coordinates or a map link (short links are followed), or null. */
    public function point(?string $text): ?GeoPoint
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if ($point = MapLink::parse($text)) {
            return $point;
        }
        if (! preg_match('~https?://\S+~u', $text, $m)) {
            return null;
        }
        $url = $m[0];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($host, self::SHORT_HOSTS, true)) {
            return null;
        }

        return Cache::remember('maplink:'.sha1($url), now()->addDay(), function () use ($url) {
            try {
                $response = Http::timeout(6)->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Dawat location reader)'])->get($url);
                $hops = $response->header('X-Guzzle-Redirect-History');
                foreach ([...array_reverse(array_filter(explode(', ', (string) $hops))), $response->effectiveUri()?->__toString() ?? ''] as $final) {
                    if ($point = MapLink::parse(urldecode($final))) {
                        return $point;
                    }
                }

                return MapLink::parse(mb_substr($response->body(), 0, 200000));
            } catch (Throwable $e) {
                Log::info('map link not followed', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Places matching an address, or the one point a link/coordinates give.
     *
     * @return list<array{label: string, lat: float, lng: float}>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }
        if ($point = $this->point($query)) {
            return [['label' => 'লিংকের জায়গা', 'lat' => $point->lat, 'lng' => $point->lng]];
        }

        return Cache::remember('geocode:'.sha1(mb_strtolower($query)), now()->addDays(7), function () use ($query) {
            try {
                return filled(config('services.google_maps.key')) ? $this->google($query) : $this->osm($query);
            } catch (Throwable $e) {
                Log::warning('geocode failed', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    private function google(string $query): array
    {
        $json = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $query,
            'components' => 'country:BD',
            'bounds' => '23.66,90.32|23.90,90.51',
            'language' => 'bn',
            'key' => config('services.google_maps.key'),
        ])->json();

        return collect($json['results'] ?? [])->take(5)->map(fn ($r) => [
            'label' => $r['formatted_address'] ?? $query,
            'lat' => (float) $r['geometry']['location']['lat'],
            'lng' => (float) $r['geometry']['location']['lng'],
        ])->filter(fn ($r) => self::inBangladesh($r['lat'], $r['lng']))->values()->all();
    }

    private function osm(string $query): array
    {
        $rows = Http::timeout(8)->withHeaders(['User-Agent' => 'Dawat/1.0 (https://dawat.cloud)'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'countrycodes' => 'bd',
                'viewbox' => '90.32,23.90,90.51,23.66',
                'limit' => 5,
                'accept-language' => 'bn,en',
            ])->json();

        return collect(is_array($rows) ? $rows : [])->map(fn ($r) => [
            'label' => $r['display_name'] ?? $query,
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lon'],
        ])->filter(fn ($r) => self::inBangladesh($r['lat'], $r['lng']))->values()->all();
    }

    private static function inBangladesh(float $lat, float $lng): bool
    {
        return $lat >= Bangladesh::MIN_LAT && $lat <= Bangladesh::MAX_LAT && $lng >= Bangladesh::MIN_LNG && $lng <= Bangladesh::MAX_LNG;
    }
}
