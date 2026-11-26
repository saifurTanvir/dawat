<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F110: an area manager pays any cook he serves at her door from the cash
 * he holds (not only on the office return round), and she confirms it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->timestamp('cook_confirmed_at')->nullable()->after('offset_settlement_id');
            $table->timestamp('cook_disputed_at')->nullable()->after('cook_confirmed_at');
        });
        // Return-round payouts already confirmed on their stop.
        DB::table('round_stops')->whereNotNull('settlement_id')->whereNotNull('cook_confirmed_at')->orderBy('id')
            ->each(fn ($s) => DB::table('settlements')->where('id', $s->settlement_id)->update(['cook_confirmed_at' => $s->cook_confirmed_at]));

        DB::table('bot_facts')->where('feature', 'F110')->where('audience', 'cook')->update([
            'body' => 'গৃহিণীর টাকা এলাকা ম্যানেজার বাসায় এসে নগদে দেন — অফিসের খাবার হলে সন্ধ্যায় খালি বক্স ফেরত দেওয়ার সময়, নইলে পরের যেকোনো দেখায়। গৃহিণী অ্যাপে বেছে নেন টাকা কখন নেবেন: প্রতিদিন (সেদিনের টাকা সেদিন), অথবা সপ্তাহে একদিন — নিজের পছন্দের বারে পুরো সপ্তাহের টাকা একসাথে। শুরুতে সবার জন্য "প্রতিদিন"। টাকা হলো খাবারের দাম থেকে দাওয়াতের ফি আর ডেলিভারি খরচ বাদে যা থাকে; অ্যাপের "আপনার টাকা"-তে এখনকার পাওনা দেখা যায়। টাকা দিলে অ্যাপে প্রশ্ন আসে — পেলে "হ্যাঁ, পেয়েছি", না পেলে বা কম পেলে "পাইনি / কম পেয়েছি"; দাওয়াত টিম দেখবে।',
            'updated_at' => now(),
        ]);
        DB::table('bot_facts')->insert([
            'topic' => 'এলাকা ম্যানেজারের "আজকের হিসাব" আর গৃহিণীকে টাকা দেওয়া',
            'body' => 'এলাকা ম্যানেজারের "আজকের তালিকা"-র উপরে "আজকের হিসাব": কত নগদ তুলেছেন, গৃহিণীদের কত দিয়েছেন, দাওয়াতকে কত দেবেন। নিচে তাঁর গৃহিণীদের পাওনা — যাঁর আজ টাকার দিন তাঁর পাশে "৳… হাতে দিয়েছি" বোতাম; চাপলে টাকা তাঁর হাতে জমা টাকা থেকে বাদ যায় আর গৃহিণী অ্যাপে নিশ্চিত করেন। সাপ্তাহিক গৃহিণীর পাশে লেখা থাকে কোন বারে নেন।',
            'audience' => 'shahidar', 'feature' => 'F110', 'is_active' => true,
            'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F110')->where('topic', 'এলাকা ম্যানেজারের "আজকের হিসাব" আর গৃহিণীকে টাকা দেওয়া')->delete();
        Schema::table('settlements', fn (Blueprint $t) => $t->dropColumn(['cook_confirmed_at', 'cook_disputed_at']));
    }
};
