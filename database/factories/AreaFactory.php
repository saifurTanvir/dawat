<?php

namespace Database\Factories;

use App\Models\Area;
use App\Support\Geo\GeoPoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        $name = 'এলাকা '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug('area-'.fake()->unique()->numberBetween(1, 999999)),
            // Somewhere inside Dhaka city.
            'center' => GeoPoint::make(fake()->randomFloat(5, 23.70, 23.88), fake()->randomFloat(5, 90.34, 90.43)),
            'radius_m' => 1500,
            'is_active' => true,
        ];
    }
}
