<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * F31: what search engines read — page meta and schema.org JSON-LD.
 * Nothing here may carry a kitchen's address or location.
 */
final class Seo
{
    public const SITE_NAME = 'দাওয়াত';

    /** @return array{title: string, description: string, image: string, url: string, canonical: string, robots: string} */
    public static function meta(string $title, string $description, ?string $path = null, string $robots = 'index,follow', ?string $image = null): array
    {
        $url = $path === null ? url()->current() : url($path);

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image ?? asset('brand/og.jpg'),
            'url' => $url,
            'canonical' => $url,
            'robots' => $robots,
        ];
    }

    /** Bangla "in": বাড্ডা → বাড্ডায়, বনানী → বনানীতে, শাহজাদপুর → শাহজাদপুরে, গুলশান ১ → গুলশান ১-এ */
    public static function in(string $place): string
    {
        $last = mb_substr($place, -1);

        return match (true) {
            $last === 'া' => $place.'য়',
            in_array($last, ['ি', 'ী', 'ু', 'ূ', 'ে', 'ৈ', 'ো', 'ৌ'], true) => $place.'তে',
            (bool) preg_match('/[\x{0995}-\x{09B9}\x{09CE}\x{09DC}-\x{09DF}]/u', $last) => $place.'ে',
            default => $place.'-এ',
        };
    }

    /** Bangla "of": বাড্ডা → বাড্ডার, শাহজাদপুর → শাহজাদপুরের, গুলশান ১ → গুলশান ১-এর */
    public static function of(string $place): string
    {
        $last = mb_substr($place, -1);

        return match (true) {
            in_array($last, ['া', 'ি', 'ী', 'ু', 'ূ', 'ে', 'ৈ', 'ো', 'ৌ'], true) => $place.'র',
            (bool) preg_match('/[\x{0995}-\x{09B9}\x{09CE}\x{09DC}-\x{09DF}]/u', $last) => $place.'ের',
            default => $place.'-এর',
        };
    }

    public static function phone(): string
    {
        return (string) config('dawat.contact.phone');
    }

    /** 01685284290 → ০১৬৮৫২৮৪২৯০ */
    public static function phoneBangla(): string
    {
        return Order::banglaDigits(self::phone());
    }

    /** 01685284290 → +8801685284290 */
    public static function phoneIntl(): string
    {
        return '+'.PhoneNumber::international(self::phone());
    }

    /** @return Collection<int, Area> */
    public static function areas(): Collection
    {
        return Area::active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Organization + WebSite: tells Google that "দাওয়াত" / "Dawat" is this service. */
    public static function siteGraph(): array
    {
        $org = array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#org',
            'name' => self::SITE_NAME,
            'alternateName' => ['Dawat', 'দাওয়াত — পাড়ার ঘরের খাবার', 'dawat.cloud'],
            'url' => url('/'),
            'logo' => asset('icons/icon-512.png'),
            'description' => 'ঢাকার '.self::of(config('dawat.seo.region')).' গৃহিণীদের ঘরে রান্না খাবার, অ্যাপে অর্ডার আর বাসায় পৌঁছে দেওয়া।',
            'telephone' => self::phoneIntl(),
            'areaServed' => self::areas()->map(fn (Area $a) => ['@type' => 'Place', 'name' => "{$a->name}, ঢাকা"])->values()->all(),
            'sameAs' => array_values(array_filter([config('dawat.contact.facebook_url')])),
        ]);

        return [
            $org,
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#site',
                'name' => self::SITE_NAME,
                'alternateName' => ['Dawat', 'dawat.cloud'],
                'url' => url('/'),
                'inLanguage' => 'bn-BD',
                'publisher' => ['@id' => url('/').'#org'],
            ],
        ];
    }

    /** @param list<array{0: string, 1: string}> $trail [name, path] from the home page down */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($trail)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => url($item[1]),
            ])->all(),
        ];
    }

    /** One <script type="application/ld+json"> body for a list of nodes. */
    public static function jsonLd(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($nodes)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }

    /**
     * robots.txt: private app screens stay out of search. nginx serves
     * public/robots.txt directly, so deploys write it with `dawat:robots`.
     */
    public static function robotsTxt(): string
    {
        $lines = ['User-agent: *'];
        foreach (['/admin', '/api/', '/livewire/', '/login', '/cook', '/regular', '/orders', '/shahidar', '/q/', '/s/'] as $path) {
            $lines[] = "Disallow: {$path}";
        }

        return implode("\n", [...$lines, 'Allow: /', '', 'Sitemap: '.url('/sitemap.xml')])."\n";
    }
}
