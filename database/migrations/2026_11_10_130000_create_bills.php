<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F39: weekly or monthly bills for regular customers who pay later, with
 * part payments and what is still due (বাকি).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // each = cash to the shahidar at every delivery (as before); weekly / monthly = a bill.
            $table->string('billing', 10)->default('each')->after('max_price_paisa');
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedSmallInteger('orders_count');
            $table->unsignedInteger('food_paisa');
            $table->unsignedInteger('delivery_paisa');
            $table->unsignedInteger('total_paisa');
            $table->unsignedInteger('paid_paisa')->default(0);
            $table->string('status', 10)->default('open')->index(); // open | partial | paid | void
            $table->date('due_on')->nullable();
            // The customer opens the bill with this link; no login needed.
            $table->char('token', 40)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_paisa');
            $table->string('method', 10); // cash | bkash | other
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->after('paid_at')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('bill_id'));
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bills');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('billing'));
    }
};
