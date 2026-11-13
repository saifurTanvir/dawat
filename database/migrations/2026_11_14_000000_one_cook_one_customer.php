<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F55: one cook, one regular customer. The customer's home pin (admin-only) to
 * find the nearest free cook, and what the platform suggested at pairing time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Exact home location. Never sent to anyone but admins.
            $table->geography('location', subtype: 'point', srid: 4326)->nullable()->after('address');
        });

        Schema::table('pairings', function (Blueprint $table) {
            $table->foreignId('suggested_kitchen_id')->nullable()->after('shahidar_id')->constrained('kitchens')->nullOnDelete();
            $table->unsignedInteger('suggested_distance_m')->nullable()->after('suggested_kitchen_id');
            $table->foreignId('suggested_shahidar_id')->nullable()->after('suggested_distance_m')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pairings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_kitchen_id');
            $table->dropConstrainedForeignId('suggested_shahidar_id');
            $table->dropColumn('suggested_distance_m');
        });
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('location'));
    }
};
