<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F61: the cook says ahead what she is cooking today; her regular customer
 * says "আজ খাব না" or "এই খাবার খাব না — অন্য কিছু চাই". The cook never sees
 * the answer; the shahidar and admins sort out the second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->string('meal_slot', 10);
            $table->string('title', 150);
            $table->string('source', 12)->default('cook'); // cook | shahidar | admin | daily_menu
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['kitchen_id', 'service_date', 'meal_slot']);
        });

        Schema::create('meal_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->string('meal_slot', 10);
            $table->string('choice', 10); // skip | change
            $table->string('wish', 300)->nullable(); // what they would like instead
            $table->string('menu_title', 150)->nullable(); // what the cook was making
            $table->foreignId('kitchen_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shahidar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('open'); // open | handled (only for change)
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('handled_note', 300)->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'service_date', 'meal_slot']);
            $table->index(['service_date', 'choice', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_choices');
        Schema::dropIfExists('menu_notices');
    }
};
