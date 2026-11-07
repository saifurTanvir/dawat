<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A payout to a cook, or cash a shahidar handed to Dawat (F28).
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // cook | shahidar
            $table->foreignId('kitchen_id')->nullable()->constrained();
            $table->foreignId('shahidar_id')->nullable()->constrained('users');
            $table->unsignedInteger('orders_count');
            $table->unsignedBigInteger('gross_paisa');
            $table->unsignedBigInteger('commission_paisa')->default(0);
            $table->unsignedBigInteger('amount_paisa');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('method', 20)->default('cash');
            $table->string('note', 200)->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cook_settlement_id')->nullable()->after('collected_by')->constrained('settlements')->nullOnDelete();
            $table->foreignId('shahidar_settlement_id')->nullable()->after('cook_settlement_id')->constrained('settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shahidar_settlement_id');
            $table->dropConstrainedForeignId('cook_settlement_id');
        });
        Schema::dropIfExists('settlements');
    }
};
