<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F69: why a follow-up was closed ("ফোনে কথা হয়েছে, কাল থেকে নেবেন"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remarks', function (Blueprint $table) {
            $table->string('done_note', 500)->nullable()->after('done_by');
        });
    }

    public function down(): void
    {
        Schema::table('remarks', function (Blueprint $table) {
            $table->dropColumn('done_note');
        });
    }
};
