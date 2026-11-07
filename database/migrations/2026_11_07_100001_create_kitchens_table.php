<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A kitchen is a home cook's profile. One per user.
        Schema::create('kitchens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            // Exact home location. Never sent to buyers; only distances are.
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();

            // pending → approved | rejected; approved → suspended
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();

            // Contact preferences shown as buttons on each post.
            $table->boolean('allow_call')->default(false);
            $table->boolean('allow_message')->default(true);
            $table->boolean('allow_comment')->default(true);
            // When set, buyers call/message this number (e.g. the area's shahidar) instead of the cook.
            $table->string('contact_phone', 11)->nullable();
            $table->string('contact_name')->nullable();

            // Reserved for later phases.
            $table->foreignId('shahidar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bfsa_reg_no')->nullable();
            $table->decimal('rating_avg', 3, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchens');
    }
};
