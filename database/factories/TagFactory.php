<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'ট্যাগ '.fake()->unique()->numberBetween(1, 9999),
            'slug' => 'tag-'.fake()->unique()->numberBetween(1, 999999),
            'type' => TagType::Other,
            'is_active' => true,
        ];
    }
}
