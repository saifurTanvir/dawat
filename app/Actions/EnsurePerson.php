<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Illuminate\Validation\ValidationException;

/**
 * Finds the user with this phone, or creates one the way OTP sign-up would,
 * so admins can add shahidars and customers who then sign in with their phone.
 */
class EnsurePerson
{
    public function __invoke(string $phone, ?string $name, string $role, string $field = 'phone'): User
    {
        $normalized = PhoneNumber::normalize($phone);
        if (! $normalized) {
            throw ValidationException::withMessages([$field => 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)।']);
        }

        $user = User::firstOrCreate(['phone' => $normalized], ['status' => UserStatus::Active]);
        if ($user->wasRecentlyCreated) {
            $user->assignRole(Roles::BUYER);
        }
        if (filled($name) && blank($user->name)) {
            $user->forceFill(['name' => trim($name)])->save();
        }
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
