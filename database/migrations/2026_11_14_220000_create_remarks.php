<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F63: admins' remarks on a person (cook, customer, shahidar…), with an optional follow-up day. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the person it is about
            $table->string('body', 1000);
            $table->date('follow_up_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['follow_up_on', 'done_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remarks');
    }
};
