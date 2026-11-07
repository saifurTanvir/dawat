<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An area doubles as a delivery zone; one or more shahidars serve it.
        Schema::create('area_shahidar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['area_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_shahidar');
    }
};
