<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F41: when a person may be called. Empty = Dawat's default hours.
 * On a day with a box or a cooking, the delivery window of that meal is
 * always open (Dawat fixes it), so a missing address can still be sorted out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('call_from')->nullable()->after('status');
            $table->time('call_to')->nullable()->after('call_from');
            // any | call | whatsapp: how they like to be reached.
            $table->string('contact_prefer', 10)->default('any')->after('call_to');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['call_from', 'call_to', 'contact_prefer']));
    }
};
