<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F56: what comes off the cook's price — Dawat's fee and a delivery share —
 * set for everyone, per area or per cook, and fixed on each box when it is made.
 *
 * *_type: 'fixed' (value = paisa per plate) or 'percent' (value = hundredths of
 * a percent, 1000 = 10%). Null on an area or cook means "use the next level".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (['areas', 'kitchens'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('platform_fee_type', 10)->nullable();
                $table->unsignedInteger('platform_fee_value')->nullable();
                $table->string('delivery_cut_type', 10)->nullable();
                $table->unsignedInteger('delivery_cut_value')->nullable();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('platform_fee_paisa')->default(0)->after('delivery_fee_paisa');
            $table->unsignedInteger('cook_delivery_paisa')->default(0)->after('platform_fee_paisa');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->unsignedInteger('delivery_paisa')->default(0)->after('commission_paisa');
        });

        // Keep today's behaviour: the old ORDER_COMMISSION_PERCENT becomes the
        // everyone-level Dawat fee; no delivery share until an admin sets one.
        $percent = (float) config('dawat.orders.commission_percent', 0);
        DB::table('app_settings')->insert([
            'key' => 'cook_fees',
            'value' => json_encode([
                'platform_fee_type' => 'percent',
                'platform_fee_value' => (int) round($percent * 100),
                'delivery_cut_type' => 'fixed',
                'delivery_cut_value' => 0,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Boxes not yet paid out keep being charged the old way.
        if ($percent > 0) {
            DB::table('orders')->whereNull('cook_settlement_id')->where('payment_method', '!=', 'cash_to_cook')
                ->update(['platform_fee_paisa' => DB::raw('ROUND(food_paisa * '.$percent.' / 100)')]);
        }
    }

    public function down(): void
    {
        Schema::table('settlements', fn (Blueprint $table) => $table->dropColumn('delivery_paisa'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['platform_fee_paisa', 'cook_delivery_paisa']));
        foreach (['areas', 'kitchens'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['platform_fee_type', 'platform_fee_value', 'delivery_cut_type', 'delivery_cut_value']));
        }
        Schema::dropIfExists('app_settings');
    }
};
