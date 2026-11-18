<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F68: an office's lunch goes as one drop — its employees are regular
 * customers of the company; their boxes travel together and are handed to
 * the office's contact person once, with one code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address', 300);
            $table->foreignId('area_id')->constrained();
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('drop_note', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('area_id')->constrained()->nullOnDelete();
        });

        Schema::create('office_drops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->string('meal_slot', 20);
            $table->string('code_hash')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirm_method', 30)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'service_date', 'meal_slot']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('office_drop_id')->nullable()->after('pairing_id')->constrained()->nullOnDelete();
        });

        // A bag sticker belongs to a drop rather than one box.
        Schema::table('stickers', function (Blueprint $table) {
            $table->foreignId('office_drop_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stickers', fn (Blueprint $t) => $t->dropConstrainedForeignId('office_drop_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('office_drop_id'));
        Schema::dropIfExists('office_drops');
        Schema::table('customers', fn (Blueprint $t) => $t->dropConstrainedForeignId('company_id'));
        Schema::dropIfExists('companies');
    }
};
