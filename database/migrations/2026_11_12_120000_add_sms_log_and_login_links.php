<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F50: when the login SMS does not arrive. Every send is logged; the admin
 * can send a code on WhatsApp or a one-time login link; accounts remember
 * how their number was confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 15)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 30);
            // sent | failed | reported (the person said no code came) | whatsapp (admin sent it by hand)
            $table->string('status', 10)->index();
            $table->string('error', 200)->nullable();
            $table->foreignId('by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('login_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('channel', 10)->default('sms')->after('ip'); // sms | whatsapp
            $table->foreignId('created_by')->nullable()->after('channel')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            // sms | whatsapp | link — how the phone number was confirmed.
            $table->string('verified_via', 10)->nullable()->after('phone_verified_at');
            $table->foreignId('verified_by')->nullable()->after('verified_via')->constrained('users')->nullOnDelete();
        });
        DB::table('users')->whereNotNull('phone_verified_at')->update(['verified_via' => 'sms']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => [$t->dropConstrainedForeignId('verified_by'), $t->dropColumn('verified_via')]);
        Schema::table('otp_codes', fn (Blueprint $t) => [$t->dropConstrainedForeignId('created_by'), $t->dropColumn('channel')]);
        Schema::dropIfExists('login_links');
        Schema::dropIfExists('sms_logs');
    }
};
