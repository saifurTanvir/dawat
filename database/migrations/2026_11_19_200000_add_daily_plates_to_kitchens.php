<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F77: how many plates a day the cook said she can give (1 or 2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->unsignedTinyInteger('daily_plates')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropColumn('daily_plates');
        });
    }
};
