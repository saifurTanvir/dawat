<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F110: the evening return round. The area manager takes the office's empty
 * boxes back to each cook, house by house, and pays her from the cash he
 * holds — every day, or once a week on her chosen day. She confirms in the
 * app that she got the boxes and the money.
 * The bot learns it too (CLAUDE.md: bot_facts change with the feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->string('pay_cycle', 10)->default('daily')->after('daily_plates'); // daily | weekly
            $table->unsignedTinyInteger('pay_weekday')->nullable()->after('pay_cycle'); // 0 = Sunday … 6 = Saturday
        });

        Schema::table('round_stops', function (Blueprint $table) {
            $table->string('kind', 10)->default('pickup')->after('shahidar_id'); // pickup | return
            $table->unique(['office_drop_id', 'shahidar_id', 'kitchen_id', 'kind'], 'round_stops_drop_kind_unique');
        });
        Schema::table('round_stops', function (Blueprint $table) {
            $table->dropUnique(['office_drop_id', 'shahidar_id', 'kitchen_id']);
            $table->foreignId('settlement_id')->nullable()->after('picked_at')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('paid_paisa')->default(0)->after('settlement_id');
            $table->timestamp('cook_confirmed_at')->nullable()->after('paid_paisa');
            $table->timestamp('cook_disputed_at')->nullable()->after('cook_confirmed_at');
        });

        Schema::table('settlements', function (Blueprint $table) {
            // A cook paid at her door from the cash this area manager collected…
            $table->foreignId('cash_from_shahidar_id')->nullable()->after('settled_by')->constrained('users')->nullOnDelete();
            // …and the hand-over of his cash to Dawat that took it into account.
            $table->foreignId('offset_settlement_id')->nullable()->after('cash_from_shahidar_id')->constrained('settlements')->nullOnDelete();
            $table->unsignedBigInteger('cook_paid_paisa')->default(0)->after('commission_paisa');
        });

        $sort = (int) DB::table('bot_facts')->max('sort_order');
        DB::table('bot_facts')->insert([
            [
                'topic' => 'গৃহিণী টাকা কখন আর কীভাবে পান, খালি বক্স কীভাবে ফেরত আসে',
                'body' => 'অফিসের খাবারের খালি বক্স সন্ধ্যায় এলাকা ম্যানেজার বাসায় এসে ফেরত দেন, আর তখনই নগদে টাকা দেন। গৃহিণী অ্যাপে বেছে নেন টাকা কখন নেবেন: প্রতিদিন (সেদিনের টাকা সেদিন সন্ধ্যায়), অথবা সপ্তাহে একদিন — নিজের পছন্দের বারে পুরো সপ্তাহের টাকা একসাথে। শুরুতে সবার জন্য "প্রতিদিন"। টাকা হলো খাবারের দাম থেকে দাওয়াতের ফি আর ডেলিভারি খরচ বাদে যা থাকে। আসার আগে খবর যায় (প্রায় ৩০ মিনিট আগে, আর আগের বাসা থেকে রওনা দিলে) — কয়টা বক্স আর আজ কত টাকা। বক্স আর টাকা পেলে অ্যাপে "হ্যাঁ, পেয়েছি" চাপবেন; না পেলে বা কম পেলে "পাইনি / কম পেয়েছি" — দাওয়াত টিম দেখবে।',
                'audience' => 'cook',
                'feature' => 'F110',
                'is_active' => true,
                'sort_order' => $sort + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'সন্ধ্যার ফেরত রাউন্ড: খালি বক্স আর গৃহিণীর টাকা',
                'body' => 'অফিসে খাবার বুঝিয়ে দেওয়ার পর সন্ধ্যায় এলাকা ম্যানেজার অফিস থেকে খালি বক্সগুলো নেন, তারপর অ্যাপের "ফেরত রাউন্ড"-এ একে একে প্রত্যেক গৃহিণীর বাসায় যান — ম্যাপে পথ, কল/WhatsApp। প্রতিটা বাসায় অ্যাপ বলে দেয় কয়টা বক্স ফেরত আর আজ কত টাকা দিতে হবে (যিনি সাপ্তাহিক নেন, শুধু তাঁর বারেই পুরো সপ্তাহের টাকা)। টাকা যায় তাঁর হাতে জমা গ্রাহকের টাকা থেকে; দাওয়াতকে জমা দেওয়ার হিসাব থেকে সেটা নিজে বাদ যায়। "বক্স ও টাকা দিয়েছি" চাপলে গৃহিণী অ্যাপে নিশ্চিত করেন।',
                'audience' => 'shahidar',
                'feature' => 'F110',
                'is_active' => true,
                'sort_order' => $sort + 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F110')->delete();
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offset_settlement_id');
            $table->dropConstrainedForeignId('cash_from_shahidar_id');
            $table->dropColumn('cook_paid_paisa');
        });
        Schema::table('round_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_id');
            $table->dropColumn(['paid_paisa', 'cook_confirmed_at', 'cook_disputed_at']);
            $table->unique(['office_drop_id', 'shahidar_id', 'kitchen_id']);
        });
        Schema::table('round_stops', function (Blueprint $table) {
            $table->dropUnique('round_stops_drop_kind_unique');
            $table->dropColumn('kind');
        });
        Schema::table('kitchens', fn (Blueprint $t) => $t->dropColumn(['pay_cycle', 'pay_weekday']));
    }
};
