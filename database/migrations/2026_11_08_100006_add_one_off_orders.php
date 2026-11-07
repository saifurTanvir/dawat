<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F29: "নিতে চাই" — a feed buyer books plates from one post.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('kind', 10)->default('regular')->after('id')->index();
            $table->foreignId('buyer_id')->nullable()->after('customer_id')->constrained('users');
            $table->string('receive', 10)->nullable()->after('buyer_id'); // delivery | self
            $table->string('delivery_address', 255)->nullable()->after('receive');
            $table->string('buyer_note', 200)->nullable()->after('delivery_address');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreignId('shahidar_id')->nullable()->change();
        });

        Schema::table('kitchens', function (Blueprint $table) {
            $table->boolean('allow_order')->default(true)->after('allow_comment');
        });

        Schema::table('users', function (Blueprint $table) {
            // Set after repeated no-shows; an admin can lift it.
            $table->timestamp('order_blocked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('order_blocked_until'));
        Schema::table('kitchens', fn (Blueprint $table) => $table->dropColumn('allow_order'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_id');
            $table->dropColumn(['kind', 'receive', 'delivery_address', 'buyer_note']);
        });
    }
};
