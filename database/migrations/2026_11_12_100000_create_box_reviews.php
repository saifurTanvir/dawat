<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F47: a review belongs to a delivered box, not to a post, so it can be
 * given up to three days later. Only the stars are public (the kitchen's
 * average); the words are for the cook, her shahidar and the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->string('body', 500)->nullable();
            $table->string('reply', 500)->nullable();
            $table->foreignId('reply_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reply_role', 10)->nullable(); // cook | shahidar | admin
            $table->timestamp('replied_at')->nullable();
            $table->boolean('reply_hidden')->default(false);
            $table->timestamps();
            $table->index(['kitchen_id', 'created_at']);
        });

        Schema::table('kitchens', function (Blueprint $table) {
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', fn (Blueprint $table) => $table->dropColumn('rating_count'));
        Schema::dropIfExists('box_reviews');
    }
};
