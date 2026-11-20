<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F83: a check-in can say how many new messages were found and, per chat, what they wrote and what was done. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_heartbeats', function (Blueprint $table) {
            $table->unsignedSmallInteger('found')->nullable()->after('sent');
            $table->json('details')->nullable()->after('problem');
        });
    }

    public function down(): void
    {
        Schema::table('ops_heartbeats', function (Blueprint $table) {
            $table->dropColumn(['found', 'details']);
        });
    }
};
