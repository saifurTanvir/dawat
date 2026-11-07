<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            // How the live contact number was confirmed: otp | admin
            $table->string('contact_verified_via', 10)->nullable()->after('contact_name');
            $table->timestamp('contact_verified_at')->nullable()->after('contact_verified_via');

            // A requested number waits here until confirmed; buyers keep reaching the cook meanwhile.
            $table->string('pending_contact_phone', 11)->nullable()->after('contact_verified_at');
            $table->string('pending_contact_name')->nullable()->after('pending_contact_phone');
            $table->string('pending_contact_method', 10)->nullable()->index()->after('pending_contact_name');
            $table->timestamp('pending_contact_requested_at')->nullable()->after('pending_contact_method');
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropColumn([
                'contact_verified_via', 'contact_verified_at',
                'pending_contact_phone', 'pending_contact_name', 'pending_contact_method', 'pending_contact_requested_at',
            ]);
        });
    }
};
