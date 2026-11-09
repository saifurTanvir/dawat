<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F37: free phone notifications (Web Push) for each device a person turned them on. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key', 120);
            $table->string('auth_token', 60);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
