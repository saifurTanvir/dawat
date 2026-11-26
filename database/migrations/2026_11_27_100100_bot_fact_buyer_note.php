<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F29: a feed order's buyer name and note reach the cook. */
return new class extends Migration
{
    public function up(): void
    {
        $sort = (int) DB::table('bot_facts')->max('sort_order');
        DB::table('bot_facts')->insert([
            [
                'topic' => 'ফিড থেকে অর্ডার: নাম আর নোট',
                'body' => 'ফিড থেকে "নিতে চাই" দিয়ে অর্ডার করলে ক্রেতার নাম আর যা লিখেছেন (যেমন "রাত ৯টায় নিতে আসব" বা "গেটে এসে ফোন দেবেন") গৃহিণী অ্যাপে আর SMS-এ দেখেন। অ্যাকাউন্টে নাম না থাকলে প্রথম অর্ডারে একবার নাম জিজ্ঞেস করা হয়।',
                'audience' => 'customer', 'feature' => 'F29', 'is_active' => true, 'sort_order' => $sort + 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'topic' => 'নতুন অর্ডারে ক্রেতার নাম আর নোট',
                'body' => 'ফিড থেকে কেউ অর্ডার করলে গৃহিণী অ্যাপের "আজকের অর্ডার"-এ আর SMS-এ ক্রেতার নাম আর ক্রেতা যা লিখেছেন তা দেখেন — কখন নিতে আসবেন, কোথায় ফোন দেবেন ইত্যাদি। নিয়মিত গ্রাহকের নাম আগের মতোই দেখানো হয় না।',
                'audience' => 'cook', 'feature' => 'F29', 'is_active' => true, 'sort_order' => $sort + 2, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F29')->whereIn('topic', ['ফিড থেকে অর্ডার: নাম আর নোট', 'নতুন অর্ডারে ক্রেতার নাম আর নোট'])->delete();
    }
};
