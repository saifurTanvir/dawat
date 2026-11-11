<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F43: the admin or a shahidar can post today's food for a cook, and a cook
 * can keep a daily menu that posts itself; "আজ রান্না নেই" stops it for a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // cook | admin | shahidar | auto (daily menu)
            $table->string('source', 10)->default('cook')->after('status');
            $table->foreignId('posted_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });

        Schema::create('kitchen_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->string('meal_slot', 20);
            $table->string('title', 100);
            $table->unsignedInteger('price_paisa');
            $table->unsignedTinyInteger('plates_total');
            $table->unsignedTinyInteger('available_hours')->default(3);
            $table->time('post_at');
            $table->json('weekdays');
            $table->string('note', 300)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['kitchen_id', 'meal_slot']);
        });

        Schema::create('kitchen_days_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('meal_slot', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['kitchen_id', 'date', 'meal_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_days_off');
        Schema::dropIfExists('kitchen_menus');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('source');
        });
    }
};
