<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F68: a link that lets one cook drop her home pin, no login. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pin_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            // What she sent (kept even when it goes straight onto the kitchen).
            $table->geography('submitted_location', subtype: 'point', srid: 4326)->nullable();
            $table->string('mode', 10)->nullable(); // home = phone GPS at home, map = she placed it on the map
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->unsignedInteger('distance_m')->nullable(); // from her area's centre
            $table->boolean('flagged')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->string('review', 10)->nullable(); // accepted / rejected
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['flagged', 'review']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_links');
    }
};
