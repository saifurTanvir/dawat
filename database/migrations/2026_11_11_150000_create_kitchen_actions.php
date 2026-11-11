<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F46: what the admin or a shahidar did for a cook, so she and the team can see it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 10); // admin | shahidar
            $table->string('action', 20);
            $table->string('details', 300);
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_actions');
    }
};
