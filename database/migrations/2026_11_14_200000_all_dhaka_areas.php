<?php

use App\Models\Area;
use App\Support\Geo\GeoPoint;
use App\Support\Geo\ServiceAreas;
use Illuminate\Database\Migrations\Migration;

/**
 * F60: Dawat now covers all of Dhaka city. Adds every area in ServiceAreas,
 * switches them on and orders them. Areas an admin already edited keep their
 * name, centre, radius and delivery fee; starter areas that were switched off
 * get the new name and centre. New areas charge the delivery fee most areas do.
 */
return new class extends Migration
{
    private const STARTER = ['mirpur-10', 'mohammadpur', 'dhanmondi', 'uttara', 'motijheel'];

    public function up(): void
    {
        $fee = (int) (Area::where('is_active', true)->selectRaw('delivery_fee_paisa, COUNT(*) AS n')
            ->groupBy('delivery_fee_paisa')->orderByDesc('n')->value('delivery_fee_paisa') ?? 0);

        foreach (ServiceAreas::all() as $order => [$slug, $name, $lat, $lng, $radius]) {
            $area = Area::firstOrNew(['slug' => $slug]);
            if (! $area->exists) {
                $area->fill(['name' => $name, 'center' => GeoPoint::make($lat, $lng), 'radius_m' => $radius, 'delivery_fee_paisa' => $fee]);
            } elseif (in_array($slug, self::STARTER, true) && ! $area->is_active) {
                $area->fill(['name' => $name, 'center' => GeoPoint::make($lat, $lng), 'radius_m' => $radius]);
                if (! $area->delivery_fee_paisa) {
                    $area->delivery_fee_paisa = $fee;
                }
            }
            $area->fill(['is_active' => true, 'sort_order' => $order])->save();
        }
    }

    public function down(): void
    {
        // Data change: nothing to undo safely.
    }
};
