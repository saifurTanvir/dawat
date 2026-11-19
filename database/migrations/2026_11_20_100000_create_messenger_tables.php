<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F80: the Facebook page's Messenger conversations, answered by the server
 * (Messenger API webhook) instead of a browser loop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_threads', function (Blueprint $table) {
            $table->id();
            $table->string('psid', 40)->unique(); // the person's page-scoped id
            $table->string('name', 120)->nullable(); // their Facebook name
            $table->json('facts')->nullable(); // what they told us: name, phone, area_id, area_text, cook, plates
            $table->foreignId('kitchen_id')->nullable()->constrained()->nullOnDelete(); // made from this chat
            $table->foreignId('call_request_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('bot_off')->default(false); // an admin turned the bot off for this chat
            $table->timestamp('paused_until')->nullable(); // a person answered from the page; the bot waits
            $table->boolean('needs_human')->default(false);
            $table->string('human_reason', 255)->nullable();
            $table->string('send_error', 255)->nullable();
            $table->timestamp('last_in_at')->nullable()->index();
            $table->timestamp('last_out_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messenger_thread_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // in | out
            $table->string('by', 10)->default('person'); // person | bot | admin | page (someone on the page / Meta's auto-reply)
            $table->string('mid', 120)->nullable()->unique();
            $table->text('text')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_messages');
        Schema::dropIfExists('messenger_threads');
    }
};
