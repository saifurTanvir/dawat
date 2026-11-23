<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F108: a customer who taps "আগ্রহী — কথা বলুন" goes on "যোগাযোগ করতে হবে" even without a number;
 * the entry keeps the Messenger chat so the owner can talk there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_requests', function (Blueprint $table) {
            $table->string('phone', 11)->nullable()->change();
            $table->foreignId('messenger_thread_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('call_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('messenger_thread_id');
        });
    }
};
