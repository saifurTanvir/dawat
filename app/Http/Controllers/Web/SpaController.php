<?php

namespace App\Http\Controllers\Web;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Serves the Vue app. For /p/{id} it adds Open Graph tags, so a link sent on
 * WhatsApp or Facebook previews with the share card (F19). F31: the home page
 * carries readable text and schema.org data, private screens are noindex,
 * and paths the app does not know answer 404.
 */
class SpaController extends Controller
{
    /** Screens behind a login or a QR code: useful to people, not to search. */
    private const PRIVATE = ['login', 'cook', 'cook/setup', 'cook/new', 'cook/posts', 'cook/sales', 'history', 'regular', 'orders', 'shahidar'];

    public function __invoke(Request $request): Response
    {
        $path = trim($request->path(), '/');

        if ($path === '') {
            $areas = Seo::areas();

            return response()->view('spa', [
                'meta' => Seo::meta(
                    'দাওয়াত — ঢাকায় পাড়ার ঘরের খাবার, দুপুরের লাঞ্চ | Dawat',
                    Seo::of(config('dawat.seo.region')).' গৃহিণীদের ঘরে রান্না খাবার। আজকের দুপুর-রাতের রান্না দেখুন, অ্যাপেই অর্ডার করুন, বা অফিসে প্রতিদিনের লাঞ্চ নিন। Homemade food in Dhaka.',
                    '/',
                ),
                'jsonLd' => Seo::jsonLd(Seo::siteGraph()),
                'intro' => true,
                'areas' => $areas,
            ]);
        }

        if ($path === 'app') {
            return response()->view('spa', ['meta' => Seo::meta(
                'দাওয়াত অ্যাপ ইনস্টল করুন — পাড়ার ঘরের খাবার',
                'দাওয়াত অ্যাপ ফোনের হোম স্ক্রিনে রাখুন। Play Store লাগে না, এক চাপে খুলবে।',
                '/app',
            )]);
        }

        if (in_array($path, self::PRIVATE, true) || preg_match('#^(q|s|l)/[^/]+$|^shahidar/kitchens/\d+$#', $path)) {
            return response()->view('spa', ['meta' => $this->defaults('noindex,nofollow')]);
        }

        // Vue shows its "not found" screen; search engines get a real 404.
        return response()->view('spa', ['meta' => $this->defaults('noindex,follow')], 404);
    }

    public function post(string $id): Response
    {
        $post = ctype_digit($id) ? Post::with(['kitchen', 'area', 'images'])->find($id) : null;

        if (! $post || $post->status === PostStatus::Hidden || ! $post->kitchen->isApproved()) {
            return response()->view('spa', ['meta' => $this->defaults('noindex,follow')], 404);
        }

        $price = '৳'.Order::banglaDigits((string) round($post->price_paisa / 100));
        // A post lives for one meal; afterwards it stays reachable but leaves the index.
        $live = Post::visibleNow()->whereKey($post->id)->exists();

        return response()->view('spa', ['meta' => Seo::meta(
            "{$post->title} · {$price} — {$post->kitchen->display_name}",
            Str::limit(collect([
                $post->area ? Seo::in($post->area->name).' ঘরের রান্না' : 'ঘরের রান্না',
                $post->note,
            ])->filter()->implode('। '), 180),
            '/p/'.$post->id,
            $live ? 'index,follow' : 'noindex,follow',
            $post->shareImageUrl() ?? $post->images->first()?->url(),
        )]);
    }

    /** @return array<string, string|null> */
    private function defaults(string $robots): array
    {
        return [
            ...Seo::meta(Seo::SITE_NAME.' — পাড়ার ঘরের খাবার', 'আশেপাশের গৃহিণীদের আজকের রান্না, কাছেরটা আগে।', null, $robots),
            'canonical' => null,
        ];
    }
}
