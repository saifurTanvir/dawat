<?php

use App\Models\Area;
use App\Support\Geo\GeoPoint;
use App\Support\Geo\ServiceAreas;
use Illuminate\Database\Migrations\Migration;

/**
 * F73: add Sreepur (Gazipur), where a cook has asked to join.
 * Same idempotent pass over ServiceAreas::BEYOND as F69: existing areas are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fee = (int) (Area::where('is_active', true)->selectRaw('delivery_fee_paisa, COUNT(*) AS n')
            ->groupBy('delivery_fee_paisa')->orderByDesc('n')->value('delivery_fee_paisa') ?? 0);
        $order = (int) Area::max('sort_order');

        foreach (ServiceAreas::BEYOND as [$slug, $name, $lat, $lng, $radius]) {
            if (Area::where('slug', $slug)->exists()) {
                continue;
            }
            Area::create([
                'slug' => $slug,
                'name' => $name,
                'center' => GeoPoint::make($lat, $lng),
                'radius_m' => $radius,
                'delivery_fee_paisa' => $fee,
                'is_active' => true,
                'sort_order' => ++$order,
            ]);
        }
    }

    public function down(): void
    {
        // Data change: nothing to undo safely.
    }
};
