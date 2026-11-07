<?php

use App\Models\Kitchen;
use App\Support\Geo\LocationFuzzer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            // The home moved 120–250 m at random; the only location buyers' distances use.
            $table->geography('public_location', subtype: 'point', srid: 4326)->nullable()->after('location');
        });

        // Existing kitchens: create a public point and move their posts onto it.
        Kitchen::whereNotNull('location')->each(function (Kitchen $kitchen) {
            $public = LocationFuzzer::fuzz($kitchen->location);
            $kitchen->forceFill(['public_location' => $public])->saveQuietly();
            DB::table('posts')->where('kitchen_id', $kitchen->id)->update(['location' => $public->toExpression()]);
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropColumn('public_location');
        });
    }
};
