<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F111: the area list when adding a regular customer shows only where Dawat
 * delivers now — Badda and the areas around it. Admins tick more areas in
 * "এলাকা" when delivery opens there.
 */
return new class extends Migration
{
    public const START = [
        'uttar-badda', 'madhya-badda', 'dakkhin-badda', 'merul-badda', 'shahjadpur',
        'bashundhara', 'nadda', 'gulshan-1', 'gulshan-2', 'banani', 'rampura', 'banasree',
    ];

    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('for_customers')->default(false)->after('is_active');
        });
        DB::table('areas')->whereIn('slug', self::START)->update(['for_customers' => true]);
    }

    public function down(): void
    {
        Schema::table('areas', fn (Blueprint $t) => $t->dropColumn('for_customers'));
    }
};
