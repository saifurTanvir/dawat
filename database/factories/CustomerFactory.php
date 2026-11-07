<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Area;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'area_id' => Area::factory(),
            'address' => 'বাসা '.fake()->numberBetween(1, 99).', রোড '.fake()->numberBetween(1, 20),
            'status' => CustomerStatus::Waiting,
            'meal_slots' => ['lunch'],
            'plates' => 1,
            'weekdays' => Customer::ALL_WEEKDAYS,
        ];
    }
}
