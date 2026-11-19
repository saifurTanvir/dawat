<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F70: what became of a finished call — added as a cook, customer, shahidar, an account, or left out. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_requests', function (Blueprint $table) {
            $table->string('onboard_result', 20)->nullable()->after('done_by');
            $table->timestamp('onboarded_at')->nullable()->after('onboard_result');
        });
    }

    public function down(): void
    {
        Schema::table('call_requests', function (Blueprint $table) {
            $table->dropColumn(['onboard_result', 'onboarded_at']);
        });
    }
};
