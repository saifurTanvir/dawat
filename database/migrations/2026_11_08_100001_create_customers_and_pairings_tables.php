<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A regular customer: someone who gets home food every day from one cook.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained();
            // Where the shahidar delivers. Seen only by admins and the paired shahidar.
            $table->string('address', 255);
            $table->string('note', 300)->nullable();
            $table->string('status', 20)->default('waiting')->index();
            // Standing booking (F26): which meals, how many plates, which weekdays (0 = Sunday).
            $table->json('meal_slots');
            $table->unsignedTinyInteger('plates')->default(1);
            $table->json('weekdays');
            $table->string('source', 10)->default('app');
            $table->timestamps();
        });

        // Customer ↔ cook ↔ shahidar. One open pairing per customer; history is kept.
        Schema::create('pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained();
            $table->foreignId('shahidar_id')->constrained('users');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'ended_at']);
            $table->index(['kitchen_id', 'ended_at']);
            $table->index(['shahidar_id', 'ended_at']);
        });

        Schema::table('kitchens', function (Blueprint $table) {
            // How many regular customers this cook can feed every day.
            $table->unsignedSmallInteger('max_regular_customers')->default(8)->after('allow_comment');
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', fn (Blueprint $table) => $table->dropColumn('max_regular_customers'));
        Schema::dropIfExists('pairings');
        Schema::dropIfExists('customers');
    }
};
