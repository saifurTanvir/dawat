<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F101: one row per number a notice SMS went to, so nobody gets the same notice twice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 40);
            $table->string('phone', 11);
            $table->timestamp('sent_at');
            $table->unique(['campaign', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_broadcasts');
    }
};
