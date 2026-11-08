<?php

namespace Database\Seeders;

use App\Enums\KitchenStatus;
use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One login per role for trying the app by hand:
 *   php artisan db:seed --class=TestUsersSeeder
 * Password 123456 everywhere, so it only runs with APP_ENV=local.
 */
class TestUsersSeeder extends Seeder
{
    public const PASSWORD = '123456';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('TestUsersSeeder only runs with APP_ENV=local (weak test passwords).');
        }

        $this->call(DatabaseSeeder::class);
        $area = Area::where('slug', 'uttar-badda')->firstOrFail();

        $admin = $this->user('01900000000', 'অ্যাডমিন', Roles::ADMIN, 'admin@dawat.test');

        $cook = $this->user('01800000001', 'টেস্ট গৃহিণী', Roles::COOK, 'cook@dawat.test');
        $kitchen = Kitchen::updateOrCreate(['user_id' => $cook->id], [
            'display_name' => 'টেস্ট রান্নাঘর',
            'area_id' => $area->id,
            'location' => GeoPoint::make(23.8075, 90.3680),
            'allow_call' => true,
            'allow_message' => true,
            'allow_comment' => true,
        ]);
        $kitchen->approve($admin);

        // Fresh sign-up waiting in the admin's approval queue.
        $pending = $this->user('01800000002', 'অপেক্ষমাণ গৃহিণী', Roles::COOK, 'pending-cook@dawat.test');
        Kitchen::updateOrCreate(['user_id' => $pending->id], [
            'display_name' => 'নতুন রান্নাঘর',
            'area_id' => $area->id,
            'location' => GeoPoint::make(23.8050, 90.3700),
            'status' => KitchenStatus::Pending,
        ]);

        $this->user('01800000003', 'টেস্ট ক্রেতা', Roles::BUYER, 'buyer@dawat.test');

        $this->command?->table(['ভূমিকা', 'ফোন', 'ইমেইল', 'পাসওয়ার্ড'], [
            ['admin', '01900000000', 'admin@dawat.test', self::PASSWORD],
            ['cook (অনুমোদিত)', '01800000001', 'cook@dawat.test', self::PASSWORD],
            ['cook (অপেক্ষমাণ)', '01800000002', 'pending-cook@dawat.test', self::PASSWORD],
            ['buyer', '01800000003', 'buyer@dawat.test', self::PASSWORD],
        ]);
    }

    private function user(string $phone, string $name, string $role, string $email): User
    {
        $user = User::where('phone', $phone)->orWhere('email', $email)->first() ?? new User;
        $user->forceFill([
            'name' => $name,
            'phone' => $phone,
            'phone_verified_at' => now(),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => self::PASSWORD,
            'status' => UserStatus::Active,
        ])->save();
        $user->syncRoles([$role]);

        return $user;
    }
}
