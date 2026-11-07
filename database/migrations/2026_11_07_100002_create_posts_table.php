<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            // Money is stored in paisa (1 taka = 100 paisa) to avoid rounding errors later.
            $table->unsignedInteger('price_paisa');
            $table->unsignedSmallInteger('plates_total');
            $table->unsignedSmallInteger('plates_left');
            $table->string('meal_slot', 20); // breakfast | lunch | snack | dinner
            $table->text('note')->nullable();
            $table->timestamp('available_until');
            // Copied from the kitchen when published, so feed queries need one table.
            $table->geography('location', subtype: 'point', srid: 4326);
            $table->string('status', 20)->default('live'); // live | sold_out | expired | hidden
            $table->string('share_image_path')->nullable();
            $table->timestamp('published_at')->nullable();

            // Reserved for later phases.
            $table->foreignId('shahidar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('preorder_for_date')->nullable();

            $table->timestamps();

            $table->spatialIndex('location');
            $table->index(['status', 'available_until']);
        });

        Schema::create('post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_images');
        Schema::dropIfExists('posts');
    }
};
