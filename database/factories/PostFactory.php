<?php

namespace Database\Factories;

use App\Enums\MealSlot;
use App\Enums\PostStatus;
use App\Models\Kitchen;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $plates = fake()->numberBetween(1, 4);

        return [
            'kitchen_id' => Kitchen::factory()->approved(),
            'title' => fake()->randomElement(['ভাত, মুরগির ঝোল, ডাল', 'খিচুড়ি ও ডিম ভুনা', 'রুই মাছের কালিয়া, ভাত', 'ভাপা পিঠা']),
            'price_paisa' => fake()->randomElement([12000, 15000, 18000]),
            'plates_total' => $plates,
            'plates_left' => $plates,
            'meal_slot' => MealSlot::Lunch,
            'note' => null,
            'available_until' => now()->addHours(3),
            'status' => PostStatus::Live,
            'published_at' => now(),
        ];
    }

    /** Copy the kitchen's location and area, as publishing does. */
    public function configure(): static
    {
        return $this->afterMaking(function (Post $post) {
            $kitchen = $post->kitchen;
            $post->location ??= $kitchen->public_location;
            $post->area_id ??= $kitchen->area_id;
        });
    }
}
