<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Printed QR codes (mosque notice board, office lobby…). Each opens one area's feed.
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // where it is posted, e.g. "Baitul Aman mosque gate"
            $table->string('code', 32)->unique(); // used in the URL: /q/{code}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('visible'); // visible | hidden
            $table->timestamps();

            $table->index(['post_id', 'status']);
        });

        // One row per tap on call / message / comment / share: the MVP's way to measure demand.
        Schema::create('contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // call | message | comment | share
            $table->timestamp('created_at')->useCurrent();

            $table->index(['post_id', 'type']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('reportable'); // a post, a comment or a user
            $table->string('reason', 50);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open')->index(); // open | resolved | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('contact_events');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('qr_codes');
    }
};
