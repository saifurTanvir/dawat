<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F45: the cook is told when a new regular customer takes a plate from a post she already made. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_facts')->insert([
            'topic' => 'পোস্ট দেওয়ার পরে নতুন নিয়মিত গ্রাহক এলে',
            'body' => 'গৃহিণী আজকের পোস্ট দেওয়ার পরে যদি তাঁর সাথে নতুন নিয়মিত গ্রাহক জোড়া হন, সেই পোস্ট থেকেই গ্রাহকের প্লেট বুক হয়ে যায়। গৃহিণী সাথে সাথে অ্যাপে নোটিফিকেশন আর SMS পান: "নতুন নিয়মিত গ্রাহক — আজ কত প্লেট আলাদা রাখুন, এলাকা ম্যানেজার কে নিতে আসবেন"। গ্রাহকের নাম গৃহিণীকে বলা হয় না।',
            'audience' => 'cook',
            'feature' => 'F45',
            'is_active' => true,
            'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F45')->where('topic', 'পোস্ট দেওয়ার পরে নতুন নিয়মিত গ্রাহক এলে')->delete();
    }
};
