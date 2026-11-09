<?php

use App\Models\Area;
use App\Support\Geo\GeoPoint;
use App\Support\Geo\ServiceAreas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F31: Dawat serves the Badda side of Dhaka. Adds those areas and switches
 * off the starter areas nobody uses yet.
 */
return new class extends Migration
{
    private const STARTER = ['mirpur-10', 'mohammadpur', 'dhanmondi', 'uttara', 'motijheel'];

    public function up(): void
    {
        // The starter "gulshan" area becomes Gulshan 1, keeping its id and links to it.
        if (! Area::where('slug', 'gulshan-1')->exists()) {
            Area::where('slug', 'gulshan')->update(['slug' => 'gulshan-1', 'name' => 'গুলশান ১']);
        }

        foreach (ServiceAreas::LIST as $order => [$slug, $name, $lat, $lng, $radius]) {
            $area = Area::firstOrNew(['slug' => $slug]);
            if (! $area->exists) {
                $area->fill(['name' => $name, 'center' => GeoPoint::make($lat, $lng), 'radius_m' => $radius]);
            }
            $area->fill(['is_active' => true, 'sort_order' => $order])->save();
        }

        // Starter areas outside the service area go dark unless someone already lives there.
        Area::whereIn('slug', self::STARTER)->get()->each(function (Area $area) {
            $used = DB::table('kitchens')->where('area_id', $area->id)->exists()
                || DB::table('customers')->where('area_id', $area->id)->exists();
            if (! $used) {
                $area->update(['is_active' => false, 'sort_order' => 100]);
            }
        });
    }

    public function down(): void
    {
        // Data change: nothing to undo safely.
    }
};
