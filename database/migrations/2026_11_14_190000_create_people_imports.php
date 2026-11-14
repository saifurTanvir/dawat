<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F59: customers, cooks and shahidars added from a marketing list (Excel/CSV). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 200);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('preview'); // preview | done | cancelled
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('role_added')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('people_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('people_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('phone', 20)->nullable();
            $table->string('name', 120)->nullable();
            $table->string('role', 10)->nullable();
            $table->json('data');
            // planned: new | add_role | exists | error; after running: added | role_added | skipped | failed
            $table->string('plan', 10);
            $table->string('result', 12)->nullable();
            $table->string('message', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['people_import_id', 'line']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('campaign', 80)->nullable()->after('verified_by');
            $table->string('intake_note', 300)->nullable()->after('campaign');
            $table->foreignId('people_import_id')->nullable()->after('intake_note')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('people_import_id');
            $table->dropColumn(['campaign', 'intake_note']);
        });
        Schema::dropIfExists('people_import_rows');
        Schema::dropIfExists('people_imports');
    }
};
