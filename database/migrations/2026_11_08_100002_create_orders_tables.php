<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Not on these days" for a regular customer (Dhaka calendar dates, inclusive).
        Schema::create('customer_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'ends_on']);
        });

        // One box of food for one regular customer on one day and meal (F26–F28).
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->date('service_date');
            // Running number within the day: the box code is DD-seq (০৮-৩৭).
            $table->unsignedInteger('seq');
            $table->string('meal_slot', 20);
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kitchen_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('shahidar_id')->constrained('users');
            $table->foreignId('pairing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 100)->nullable();
            $table->unsignedTinyInteger('plates');
            $table->unsignedInteger('food_paisa')->default(0);
            $table->unsignedInteger('delivery_fee_paisa')->default(0);
            $table->string('status', 20)->index();
            $table->string('cancelled_by', 20)->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->string('delivery_code_hash')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('confirm_method', 20)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('failure_reason', 200)->nullable();
            // Money (F28): cash on delivery now; payment_method leaves room for bKash.
            $table->string('payment_method', 20)->default('cash');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service_date', 'seq']);
            $table->unique(['customer_id', 'service_date', 'meal_slot']);
            $table->index(['shahidar_id', 'service_date']);
            $table->index(['kitchen_id', 'service_date']);
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->unsignedInteger('delivery_fee_paisa')->default(0)->after('radius_m');
        });
    }

    public function down(): void
    {
        Schema::table('areas', fn (Blueprint $table) => $table->dropColumn('delivery_fee_paisa'));
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customer_pauses');
    }
};
