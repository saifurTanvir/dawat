<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * F56: Dawat's fee is a flat amount per plate by default — ৳10, as the owner
 * asked. Admins can still switch it to a percent, for everyone, an area or a
 * cook. Only boxes made from now on are charged this way.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('app_settings')->where('key', 'cook_fees')->first();
        $value = $row ? json_decode($row->value, true) : ['delivery_cut_type' => 'fixed', 'delivery_cut_value' => 0];
        $value['platform_fee_type'] = 'fixed';
        $value['platform_fee_value'] = 1000;

        DB::table('app_settings')->updateOrInsert(['key' => 'cook_fees'], ['value' => json_encode($value), 'updated_at' => now()]);
        Cache::forget('app-setting:cook_fees');
    }

    public function down(): void
    {
        // The earlier percent is not kept; set it again from the panel if needed.
    }
};
