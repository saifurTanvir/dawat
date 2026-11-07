<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // How many times this printed code was opened.
            $table->unsignedInteger('scans')->default(0)->after('code');
            $table->timestamp('last_scanned_at')->nullable()->after('scans');
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn(['scans', 'last_scanned_at']);
        });
    }
};
