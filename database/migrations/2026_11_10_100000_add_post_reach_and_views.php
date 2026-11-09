<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F35: the admin can widen where a post shows (a larger radius and/or whole
 * other areas), per kitchen and per post, and see who has seen a post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            // Buyers within this many metres see every post, whatever distance they picked.
            $table->unsignedInteger('reach_m')->nullable()->after('max_regular_customers');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('reach_m')->nullable()->after('location');
            // True when reach_m or extra areas are set, so the feed can find these cheaply.
            $table->boolean('boosted')->default(false)->after('reach_m')->index();
        });

        Schema::create('kitchen_reach_areas', function (Blueprint $table) {
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->primary(['kitchen_id', 'area_id']);
        });

        Schema::create('post_reach_areas', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'area_id']);
        });

        // One row per viewer per post per day. Only the viewer's nearest area and a
        // rounded distance are kept, never their coordinates.
        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('viewer_key', 40);
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('distance_m')->nullable();
            $table->string('source', 10); // feed | detail
            $table->date('viewed_on');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['post_id', 'viewer_key', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('post_reach_areas');
        Schema::dropIfExists('kitchen_reach_areas');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn(['reach_m', 'boosted']));
        Schema::table('kitchens', fn (Blueprint $table) => $table->dropColumn('reach_m'));
    }
};
