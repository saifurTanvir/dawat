<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F79: the shahidar prints a named sticker for each of today's boxes before
 * leaving; an office's boxes also get a spot (row, column) at the office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stickers', function (Blueprint $table) {
            $table->timestamp('printed_at')->nullable()->after('bound_by');
            $table->foreignId('printed_by')->nullable()->after('printed_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('drop_row')->nullable()->after('office_drop_id');
            $table->unsignedTinyInteger('drop_col')->nullable()->after('drop_row');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['drop_row', 'drop_col']));
        Schema::table('stickers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('printed_by');
            $t->dropColumn('printed_at');
        });
    }
};
