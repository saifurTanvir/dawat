<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * F31: server-rendered pages for people arriving from Google, plus
 * sitemap.xml and robots.txt. No page shows a kitchen's address or location.
 */
class SeoController extends Controller
{
    /** /area/{slug} — "উত্তর বাড্ডায় ঘরের খাবার" with today's dishes there. */
    public function area(string $slug): View
    {
        $area = Area::active()->where('slug', $slug)->firstOrFail();
        $areas = Seo::areas();

        $posts = Post::visibleNow()
            ->where('area_id', $area->id)
            ->whereHas('kitchen', fn ($q) => $q->where('status', 'approved'))
            ->with('kitchen:id,display_name')
            ->latest('published_at')
            ->limit(20)
            ->get();

        $nearby = $areas->reject(fn (Area $a) => $a->is($area))
            ->sortBy(fn (Area $a) => $area->center && $a->center ? $area->center->distanceTo($a->center) : PHP_INT_MAX)
            ->take(4)
            ->values();

        return view('seo.area', [
            'area' => $area,
            'posts' => $posts,
            'nearby' => $nearby,
            'areas' => $areas,
            'crumb' => $area->name,
            'meta' => Seo::meta(
                Seo::in($area->name).' ঘরের খাবার — আজকের রান্না, দুপুরের লাঞ্চ | দাওয়াত',
                "{$area->name}, ঢাকার গৃহিণীদের ঘরে রান্না খাবার। আজকের দুপুর-রাতের রান্না দেখে অ্যাপেই অর্ডার করুন, বা অফিসে-বাসায় প্রতিদিনের লাঞ্চ নিন। নগদ টাকা খাবার হাতে পেয়ে।",
                "/area/{$area->slug}",
            ),
            'jsonLd' => Seo::jsonLd([
                ...Seo::siteGraph(),
                [
                    '@type' => 'Service',
                    'name' => Seo::in($area->name).' ঘরের খাবার',
                    'serviceType' => 'Home-cooked meal delivery',
                    'provider' => ['@id' => url('/').'#org'],
                    'areaServed' => ['@type' => 'Place', 'name' => "{$area->name}, ঢাকা"],
                    'url' => url("/area/{$area->slug}"),
                ],
                Seo::breadcrumbs([['দাওয়াত', '/'], [$area->name, "/area/{$area->slug}"]]),
            ]),
        ]);
    }

    public function lunch(): View
    {
        $areas = Seo::areas();

        return view('seo.lunch', [
            'areas' => $areas,
            'crumb' => 'প্রতিদিনের লাঞ্চ',
            'meta' => Seo::meta(
                'অফিসে প্রতিদিন দুপুরের ঘরের খাবার — লাঞ্চ ডেলিভারি ঢাকা | দাওয়াত',
                'অফিসে বা বাসায় রোজ দুপুরে পাড়ার গৃহিণীর ঘরের রান্না। একবার অনুরোধ, তারপর প্রতিদিন নিজে থেকে বুক। '.config('dawat.seo.region').'। Daily home-cooked lunch in Dhaka.',
                '/lunch',
            ),
            'jsonLd' => Seo::jsonLd([
                ...Seo::siteGraph(),
                [
                    '@type' => 'Service',
                    'name' => 'প্রতিদিন দুপুরের ঘরের খাবার',
                    'serviceType' => 'Daily home-cooked lunch delivery',
                    'provider' => ['@id' => url('/').'#org'],
                    'areaServed' => $areas->map(fn (Area $a) => ['@type' => 'Place', 'name' => "{$a->name}, ঢাকা"])->values()->all(),
                    'url' => url('/lunch'),
                ],
                Seo::breadcrumbs([['দাওয়াত', '/'], ['প্রতিদিনের লাঞ্চ', '/lunch']]),
            ]),
        ]);
    }

    public function rannaghor(): View
    {
        return view('seo.rannaghor', [
            'areas' => Seo::areas(),
            'crumb' => 'গৃহিণীদের জন্য',
            'meta' => Seo::meta(
                'ঘরে বসে রান্না করে আয় — গৃহিণীদের জন্য দাওয়াত',
                'বাসার রান্না থেকে কয়েক প্লেট পাড়ার মানুষের কাছে বিক্রি করুন। দোকান লাগে না, ডেলিভারি করেন শাহিদার, ঠিকানা কেউ দেখেন না। ঢাকার '.config('dawat.seo.region').'।',
                '/rannaghor',
            ),
            'jsonLd' => Seo::jsonLd([
                ...Seo::siteGraph(),
                Seo::breadcrumbs([['দাওয়াত', '/'], ['গৃহিণীদের জন্য', '/rannaghor']]),
            ]),
        ]);
    }

    public function about(): View
    {
        $areas = Seo::areas();
        $faq = [
            ['খাবার কে রান্না করেন?', 'আপনার পাড়ার গৃহিণীরা, নিজের বাসার রান্নাঘরে। প্রত্যেক গৃহিণীর সাথে দাওয়াত টিম ফোনে কথা বলে অনুমোদন দেয়।'],
            ['টাকা কীভাবে দেব?', 'খাবার হাতে পেয়ে নগদ টাকা দিন। আগে কোনো টাকা দিতে হয় না।'],
            ['কীভাবে অর্ডার করব?', 'হোম পেজে আজকের রান্না দেখুন, পছন্দ হলে "নিতে চাই" চাপুন। মোবাইল নম্বরে আসা কোড দিয়ে লগইন করলেই অর্ডার হয়ে যায়।'],
            ['অর্ডার বাতিল করা যায়?', 'হ্যাঁ, শাহিদার খাবার তোলার আগে পর্যন্ত অ্যাপ থেকে বাতিল করা যায়।'],
            ['রোজ দুপুরের খাবার নেওয়া যায়?', 'হ্যাঁ। একবার নিয়মিত খাবারের অনুরোধ করলে প্রতিদিন নিজে থেকে বুক হয়, বিরতিও দেওয়া যায়।'],
            ['কোন কোন এলাকায় দাওয়াত আছে?', $areas->pluck('name')->implode(', ').'।'],
        ];

        return view('seo.about', [
            'areas' => $areas,
            'faq' => $faq,
            'crumb' => 'দাওয়াত সম্পর্কে',
            'meta' => Seo::meta(
                'দাওয়াত সম্পর্কে — কারা রাঁধেন, কীভাবে অর্ডার, কোথায় পাওয়া যায়',
                'দাওয়াত ঢাকার '.Seo::of(config('dawat.seo.region')).' গৃহিণীদের ঘরে রান্না খাবার পৌঁছে দেয়। প্রশ্ন ও উত্তর, এলাকা আর যোগাযোগের নম্বর।',
                '/about',
            ),
            'jsonLd' => Seo::jsonLd([
                ...Seo::siteGraph(),
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => collect($faq)->map(fn ($qa) => [
                        '@type' => 'Question',
                        'name' => $qa[0],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
                    ])->all(),
                ],
                Seo::breadcrumbs([['দাওয়াত', '/'], ['দাওয়াত সম্পর্কে', '/about']]),
            ]),
        ]);
    }

    public function sitemap(): Response
    {
        $today = now()->toAtomString();
        $urls = collect([
            ['loc' => url('/'), 'lastmod' => $today, 'changefreq' => 'hourly', 'priority' => '1.0'],
            ['loc' => url('/lunch'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/rannaghor'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => url('/about'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ]);

        foreach (Seo::areas() as $area) {
            $urls->push(['loc' => url("/area/{$area->slug}"), 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '0.8']);
        }

        Post::visibleNow()
            ->whereHas('kitchen', fn ($q) => $q->where('status', 'approved'))
            ->latest('published_at')->limit(500)->get(['id', 'updated_at'])
            ->each(fn (Post $p) => $urls->push(['loc' => url("/p/{$p->id}"), 'lastmod' => $p->updated_at->toAtomString(), 'changefreq' => 'hourly', 'priority' => '0.5']));

        return response()->view('seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(): Response
    {
        return response(Seo::robotsTxt(), 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
