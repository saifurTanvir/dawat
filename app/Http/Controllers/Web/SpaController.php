<?php

namespace App\Http\Controllers\Web;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Serves the Vue app. For /p/{id} it adds Open Graph tags, so a link sent on
 * WhatsApp or Facebook previews with the share card (F19).
 */
class SpaController extends Controller
{
    public function __invoke(): View
    {
        return view('spa', ['meta' => $this->defaults()]);
    }

    public function post(string $id): View
    {
        $post = ctype_digit($id) ? Post::with(['kitchen', 'area', 'images'])->find($id) : null;

        if (! $post || $post->status === PostStatus::Hidden || ! $post->kitchen->isApproved()) {
            return view('spa', ['meta' => $this->defaults()]);
        }

        $price = '৳'.strtr((string) round($post->price_paisa / 100), ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);

        return view('spa', ['meta' => [
            'title' => "{$post->title} · {$price} — {$post->kitchen->display_name}",
            'description' => Str::limit(collect([
                $post->area ? "{$post->area->name}-এ ঘরের রান্না" : 'ঘরের রান্না',
                $post->note,
            ])->filter()->implode('। '), 180),
            'image' => $post->shareImageUrl() ?? $post->images->first()?->url(),
            'url' => url('/p/'.$post->id),
        ]]);
    }

    /** @return array<string, string|null> */
    private function defaults(): array
    {
        return [
            'title' => config('app.name').' — পাড়ার ঘরের খাবার',
            'description' => 'আশেপাশের গৃহিণীদের আজকের রান্না, কাছেরটা আগে।',
            'image' => asset('icons/icon-512.png'),
            'url' => url()->current(),
        ];
    }
}
