<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-printed QR stickers: blank until a shahidar scans one onto a box at pickup.
        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('batch', 20)->index();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('bound_at')->nullable();
            $table->foreignId('bound_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('kitchens', function (Blueprint $table) {
            // Written address for the pickup. Only admins and the cook's shahidars see it.
            $table->string('pickup_address', 255)->nullable()->after('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', fn (Blueprint $table) => $table->dropColumn('pickup_address'));
        Schema::dropIfExists('stickers');
    }
};
