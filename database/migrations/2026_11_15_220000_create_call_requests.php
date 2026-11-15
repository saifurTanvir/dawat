<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F66: people the owner has to talk to himself — call on mobile or WhatsApp, keep notes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11);
            $table->string('name', 100)->nullable();
            $table->string('reason', 1000);
            $table->string('source', 20)->default('whatsapp');
            $table->string('status', 20)->default('pending');
            $table->json('notes')->nullable(); // [{at, by, body}]
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // the account with this phone, if any
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_requests');
    }
};
