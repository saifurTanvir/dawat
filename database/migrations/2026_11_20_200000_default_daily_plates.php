<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Owner's decision (20 Nov): kitchens that never said how many plates start at 1 a day.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('kitchens')->whereNull('daily_plates')->whereIn('status', ['approved', 'pending'])->update(['daily_plates' => 1]);
    }

    public function down(): void {}
};
