<?php

namespace App\Actions;

use App\Enums\PostStatus;
use App\Events\PostPublished;
use App\Models\Kitchen;
use App\Models\Post;
use App\Services\Images\FoodPhotoProcessor;
use App\Services\Orders\BookingPlanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublishPost
{
    public function __construct(
        private readonly FoodPhotoProcessor $photos,
        private readonly BookingPlanner $bookings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StorePostRequest data
     * @param  list<UploadedFile>  $images
     */
    public function handle(Kitchen $kitchen, array $data, array $images): Post
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($kitchen, $data, $images, &$stored) {
                $post = Post::create([
                    'kitchen_id' => $kitchen->id,
                    'area_id' => $kitchen->area_id,
                    // The public (offset) point, never the real home.
                    'location' => $kitchen->public_location,
                    'title' => trim($data['title']),
                    'price_paisa' => (int) $data['price_taka'] * 100,
                    'plates_total' => (int) $data['plates_total'],
                    'plates_left' => (int) $data['plates_total'],
                    'meal_slot' => $data['meal_slot'],
                    'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                    'available_until' => now()->addHours((int) $data['available_hours']),
                    'status' => PostStatus::Live,
                    'published_at' => now(),
                ]);

                foreach (array_values($images) as $order => $file) {
                    $photo = $this->photos->store($file, 'posts/'.$post->id);
                    $stored[] = $photo['path'];
                    $post->images()->create([...$photo, 'sort_order' => $order]);
                }

                $post->tags()->sync($data['tag_ids'] ?? []);

                // F35: a kitchen the admin widened gets the same reach on every new post.
                $areaIds = $kitchen->reachAreas()->pluck('areas.id')->all();
                if ($kitchen->reach_m || $areaIds) {
                    $post->setReach($kitchen->reach_m, $areaIds);
                }

                // F26: paired regular customers get their plates first.
                $this->bookings->bookForPost($post);

                PostPublished::dispatch($post);

                return $post;
            });
        } catch (Throwable $e) {
            // The database rolled back; don't leave orphan photos behind.
            if ($stored) {
                Storage::disk(config('filesystems.media_disk'))->delete($stored);
            }
            throw $e;
        }
    }
}
