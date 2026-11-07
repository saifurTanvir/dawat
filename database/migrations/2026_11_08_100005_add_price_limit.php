<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F30: a regular customer's highest price per plate; null = no limit.
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('max_price_paisa')->nullable()->after('plates');
        });

        // F30: a box held while the customer is asked about a higher price.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ask_until')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('ask_until'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('max_price_paisa'));
    }
};
