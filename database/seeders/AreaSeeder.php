<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Support\Geo\GeoPoint;
use App\Support\Geo\ServiceAreas;
use Illuminate\Database\Seeder;

/**
 * Dawat's service areas (the Badda side of Dhaka). Centre points are
 * approximate; correct them from the admin panel.
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        // The same list the F31 migration adds; existing areas keep any admin edits.
        $areas = ServiceAreas::LIST;

        foreach ($areas as $order => [$slug, $name, $lat, $lng, $radius]) {
            Area::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'center' => GeoPoint::make($lat, $lng), 'radius_m' => $radius, 'sort_order' => $order],
            );
        }
    }
}
