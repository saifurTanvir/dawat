<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Support\Geo\GeoPoint;
use Illuminate\Database\Seeder;

/**
 * Starter areas in Dhaka. Centre points are approximate;
 * correct them from the admin panel before printing QR codes.
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['mirpur-10', 'মিরপুর ১০', 23.8069, 90.3687],
            ['mohammadpur', 'মোহাম্মদপুর', 23.7662, 90.3589],
            ['dhanmondi', 'ধানমন্ডি', 23.7461, 90.3742],
            ['uttara', 'উত্তরা', 23.8728, 90.3984],
            ['motijheel', 'মতিঝিল', 23.7330, 90.4172],
            ['gulshan', 'গুলশান', 23.7806, 90.4167],
        ];

        foreach ($areas as $order => [$slug, $name, $lat, $lng]) {
            Area::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'center' => GeoPoint::make($lat, $lng), 'radius_m' => 2000, 'sort_order' => $order],
            );
        }
    }
}
