<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\ShareCards\ShareCardData;
use App\Services\ShareCards\ShareCardRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** F19: builds the Bangla share image for a post, off the request path. */
class GenerateShareCard implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public readonly Post $post) {}

    public function handle(ShareCardRenderer $renderer): void
    {
        $post = $this->post->fresh();
        if (! $post) {
            return;
        }

        $width = (int) config('dawat.share_cards.width');
        $height = (int) config('dawat.share_cards.height');
        $html = view('share.card', ShareCardData::for($post))->render();

        $image = $renderer->render($html, $width, $height);

        $disk = Storage::disk(config('filesystems.media_disk'));
        // A new name each time so browsers and WhatsApp never show a stale cached card.
        $path = "posts/{$post->id}/share-".Str::lower(Str::random(8)).'.jpg';
        $disk->put($path, $image, 'public');

        if ($post->share_image_path) {
            $disk->delete($post->share_image_path);
        }

        $post->forceFill(['share_image_path' => $path])->saveQuietly();
    }
}
