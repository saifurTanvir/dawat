<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F82: the owner's answer of 20 Nov 2026 — pitha, achar and other home-made food sell the same way. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_facts')->where('topic', 'প্যাকেট খাবার (পিঠা, গুড়, আচার)')->update([
            'topic' => 'পিঠা, আচার, প্যাকেটের খাবার',
            'body' => 'হ্যাঁ, পিঠা, আচার, গুড়ের মতো খাবারও বিক্রি করা যাবে — একই নিয়মে। আইডিয়া হলো: ঘরে পরিবারের জন্য যা বানানো হয়, সেটাই। যেমন সন্ধ্যায় পিঠা বানালেন — অ্যাপে পোস্ট দেবেন, যিনি কিনতে চান তিনি কিনবেন। শুধু দুপুর না, সন্ধ্যার নাস্তারও নিয়মিত গ্রাহক থাকবে; শাহিদার (ডেলিভারি ভাই) খাবার নিয়ে গ্রাহককে পৌঁছে দেবেন আর টাকা এনে দেবেন।',
            'updated_at' => now(),
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void {}
};
