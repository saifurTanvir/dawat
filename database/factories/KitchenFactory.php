<?php

namespace Database\Factories;

use App\Enums\KitchenStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kitchen>
 */
class KitchenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->firstNameFemale().'র রান্নাঘর',
            'area_id' => Area::factory(),
            'location' => GeoPoint::make(fake()->randomFloat(5, 23.70, 23.88), fake()->randomFloat(5, 90.34, 90.43)),
            'status' => KitchenStatus::Pending,
            'allow_call' => false,
            'allow_message' => true,
            'allow_comment' => true,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => KitchenStatus::Approved])
            ->afterMaking(fn (Kitchen $k) => $k->approved_at = now());
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(fn () => ['location' => GeoPoint::make($lat, $lng)]);
    }

    public function notSetUp(): static
    {
        return $this->state(fn () => ['location' => null, 'area_id' => null]);
    }
}
