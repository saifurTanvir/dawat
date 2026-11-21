<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F93: people who came from the Badda ads on WhatsApp or by commenting (Messenger chats are read from their threads). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_leads', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 20)->index(); // cook | customer | manager
            $table->string('channel', 12); // whatsapp | comment
            $table->string('key', 120); // phone (WhatsApp) or comment id
            $table->string('name', 120)->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('text', 500)->nullable();
            $table->timestamp('first_at')->nullable()->index();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_leads');
    }
};
