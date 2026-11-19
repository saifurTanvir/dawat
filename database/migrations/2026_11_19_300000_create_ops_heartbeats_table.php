<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F78: the reply loops (Messenger, WhatsApp) report "still working" here; the status page reads it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->index();
            $table->unsignedSmallInteger('sent')->default(0);
            $table->unsignedInteger('pending')->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('problem')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_heartbeats');
    }
};
