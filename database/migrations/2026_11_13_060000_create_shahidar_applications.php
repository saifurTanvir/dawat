<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F53: people who want to work as a shahidar, from the public form. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shahidar_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('phone', 11)->index();
            $table->string('gender', 10);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('lives_in', 160);
            $table->string('job', 20);
            $table->string('job_note', 120)->nullable();
            $table->string('vehicle', 20);
            $table->boolean('has_license')->nullable();
            $table->boolean('has_smartphone');
            $table->boolean('has_nid');
            $table->string('hours', 20);
            $table->json('slots');
            $table->unsignedTinyInteger('days_per_week');
            $table->string('experience', 160)->nullable();
            $table->string('heard_from', 20)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->string('admin_note', 500)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shahidar_application_area', function (Blueprint $table) {
            $table->foreignId('shahidar_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->primary(['shahidar_application_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shahidar_application_area');
        Schema::dropIfExists('shahidar_applications');
    }
};
