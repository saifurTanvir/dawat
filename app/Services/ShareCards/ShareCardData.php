<?php

namespace App\Services\ShareCards;

use App\Models\Post;
use App\Support\QrImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything the card template needs, self-contained: the photo and fonts
 * are inlined as data URIs so Chrome needs no network or file access.
 */
class ShareCardData
{
    /** @return array<string, mixed> */
    public static function for(Post $post): array
    {
        $post->loadMissing(['images', 'tags', 'kitchen', 'area']);
        $cover = $post->images->first();
        $disk = Storage::disk(config('filesystems.media_disk'));

        return [
            'post' => $post,
            'title' => $post->title,
            'kitchen' => $post->kitchen->display_name,
            'area' => $post->area?->name,
            'price' => self::bnDigits(number_format($post->price_paisa / 100)),
            'meal' => $post->meal_slot->label(),
            'tags' => $post->tags->take(4)->pluck('name')->all(),
            'note' => $post->note ? Str::limit($post->note, 110, '…') : null,
            'photo' => $cover && $disk->exists($cover->path)
                ? 'data:image/webp;base64,'.base64_encode($disk->get($cover->path))
                : null,
            'qr' => QrImage::svg(url('/p/'.$post->id)),
            'url' => preg_replace('#^https?://#', '', url('/p/'.$post->id)),
            'fonts' => self::fonts(),
        ];
    }

    /** @return array<string, string> font file => data URI */
    private static function fonts(): array
    {
        return collect(glob(resource_path('fonts/*.woff2')))
            ->mapWithKeys(fn (string $f) => [basename($f, '.woff2') => 'data:font/woff2;base64,'.base64_encode(file_get_contents($f))])
            ->all();
    }

    private static function bnDigits(string $s): string
    {
        return strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
