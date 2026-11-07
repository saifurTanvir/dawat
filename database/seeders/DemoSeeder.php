<?php

namespace Database\Seeders;

use App\Enums\KitchenStatus;
use App\Enums\MealSlot;
use App\Enums\PostStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pretend neighbourhood around Mirpur 10 for trying the app locally:
 *   php artisan db:seed --class=DemoSeeder
 * Refuses to run in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $this->call(DatabaseSeeder::class);
        $area = Area::where('slug', 'mirpur-10')->firstOrFail();
        $tags = Tag::pluck('id', 'slug');

        $cooks = [
            // name, metres east, metres north, food, price, slot, plates, tags, note, colours [bg, dish]
            ['রহিমা খালার রান্নাঘর', 180, 90, 'ভাত, মুরগির ঝোল, ডাল', 150, MealSlot::Lunch, 3, ['halal', 'spice-medium'], 'লবণ একটু কম হয়েছে, যারা কম লবণ খান তাদের জন্য ভালো।', [[245, 240, 225], [190, 95, 35]]],
            ['নাসরিন আপার হেঁশেল', -420, 260, 'খিচুড়ি, ডিম ভুনা', 120, MealSlot::Lunch, 4, ['halal', 'spice-low', 'kid-friendly'], null, [[226, 178, 60], [240, 220, 170]]],
            ['শাহানার রান্না', 650, -500, 'রুই মাছের কালিয়া, ভাত, শাক', 180, MealSlot::Lunch, 2, ['halal', 'spice-high', 'oil-high'], 'ইচ্ছা করে একটু তেল বেশি দিয়ে রান্না, পুরান ঢাকার স্বাদ।', [[245, 240, 225], [170, 70, 30]]],
            ['মায়ের হাতের পিঠা', -150, -1300, 'চিতই পিঠা, সরিষা ভর্তা', 80, MealSlot::Snack, 6, ['vegetarian'], 'সন্ধ্যার নাস্তার জন্য গরম গরম।', [[235, 228, 210], [120, 140, 60]]],
            ['ফাতেমার রান্নাঘর', 2100, 900, 'সবজি খিচুড়ি', 100, MealSlot::Lunch, 3, ['vegetarian', 'diabetic-friendly', 'oil-low'], null, [[214, 170, 70], [90, 140, 70]]],
        ];

        foreach ($cooks as $i => [$name, $east, $north, $title, $price, $slot, $plates, $tagSlugs, $note, $colours]) {
            $phone = sprintf('0171000%04d', $i + 1);
            $user = User::firstOrCreate(['phone' => $phone], ['name' => Str::before($name, ' '), 'phone_verified_at' => now()]);
            $user->syncRoles([Roles::COOK]);

            $kitchen = Kitchen::updateOrCreate(['user_id' => $user->id], [
                'display_name' => $name,
                'area_id' => $area->id,
                'location' => $this->offset($area->center, $east, $north),
                'status' => KitchenStatus::Approved,
                'allow_call' => $i % 2 === 0,
                'allow_message' => true,
                'allow_comment' => true,
            ]);

            $kitchen->posts()->delete();
            $post = Post::create([
                'kitchen_id' => $kitchen->id,
                'area_id' => $area->id,
                'location' => $kitchen->public_location,
                'title' => $title,
                'price_paisa' => $price * 100,
                'plates_total' => $plates,
                'plates_left' => max(1, $plates - $i % 2),
                'meal_slot' => $slot,
                'note' => $note,
                'available_until' => now()->addHours(3),
                'status' => PostStatus::Live,
                'published_at' => now()->subMinutes(5 + $i * 17),
            ]);
            $post->tags()->sync($tags->only($tagSlugs)->values());
            $post->images()->create(['path' => $this->photo($post->id, ...$colours), 'width' => 1200, 'height' => 900]);
        }

        $this->command?->info('Demo neighbourhood ready around '.$area->name.'. Cook logins: 01710000001 … 01710000005 (OTP in the log).');
    }

    private function offset(GeoPoint $from, int $east, int $north): GeoPoint
    {
        return GeoPoint::make(
            $from->lat + $north / 111_320,
            $from->lng + $east / (111_320 * cos(deg2rad($from->lat))),
        );
    }

    /** A simple drawn plate, so the demo needs no image files. */
    private function photo(int $postId, array $bg, array $dish): string
    {
        $im = imagecreatetruecolor(1200, 900);
        imagefill($im, 0, 0, imagecolorallocate($im, 62, 92, 72));
        imagefilledellipse($im, 600, 450, 820, 820, imagecolorallocate($im, 222, 227, 224));
        imagefilledellipse($im, 600, 450, 700, 700, imagecolorallocate($im, ...$bg));
        imagefilledellipse($im, 740, 360, 300, 250, imagecolorallocate($im, ...$dish));
        imagefilledellipse($im, 470, 560, 160, 140, imagecolorallocate($im, 232, 169, 12));
        ob_start();
        imagewebp($im, null, 80);
        $path = "posts/{$postId}/demo.webp";
        Storage::disk(config('filesystems.media_disk'))->put($path, ob_get_clean());

        return $path;
    }
}
