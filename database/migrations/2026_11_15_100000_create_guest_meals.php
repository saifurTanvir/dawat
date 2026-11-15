<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F64: a regular customer asks for extra plates for a guest. Their cook answers;
 * after 5 minutes without an answer the shahidar follows up, and if the cook
 * cannot, the shahidar arranges it from another live post (or otherwise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->nullable()->constrained()->nullOnDelete(); // their cook when asked
            $table->foreignId('shahidar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('service_date');
            $table->string('meal_slot', 10);
            $table->unsignedTinyInteger('plates');
            $table->string('note', 300)->nullable();
            // asked → (cook_yes | cook_no) → arranged | cancelled
            $table->string('status', 12)->default('asked')->index();
            $table->timestamp('cook_replied_at')->nullable();
            $table->timestamp('followup_at')->nullable(); // the shahidar was told the cook had not answered
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handled_note', 300)->nullable();
            $table->timestamps();
            $table->index(['service_date', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('guest_meal_id')->nullable()->after('pairing_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('guest_meal_id'));
        Schema::dropIfExists('guest_meals');
    }
};
