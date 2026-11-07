<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Valid Bangladeshi mobile format: 01[3-9]XXXXXXXX
            'phone' => '01'.fake()->randomElement(['3', '5', '7', '8', '9']).fake()->unique()->numerify('########'),
            'phone_verified_at' => now(),
            'status' => UserStatus::Active,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Blocked]);
    }

    public function withPassword(string $email, string $password = 'password'): static
    {
        return $this->state(fn () => [
            'email' => $email,
            'email_verified_at' => now(),
            'password' => $password,
        ]);
    }

    public function role(string $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role));
    }

    public function cook(): static
    {
        return $this->role(Roles::COOK);
    }

    public function admin(): static
    {
        return $this->withPassword(fake()->unique()->safeEmail())->role(Roles::ADMIN);
    }
}
