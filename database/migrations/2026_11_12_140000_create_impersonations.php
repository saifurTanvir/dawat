<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F51: the admin (anyone but admins) or a shahidar (their own cooks) opens
 * the app as that person, to see and do what they would. Every session and
 * every change made in it is kept, and the person is told.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('actor_role', 10); // admin | shahidar
            $table->char('claim_hash', 64)->unique(); // one-time code that opens the app
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->timestamp('ends_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('impersonation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impersonation_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->string('path', 200);
            $table->unsignedSmallInteger('status');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_actions');
        Schema::dropIfExists('impersonations');
    }
};
