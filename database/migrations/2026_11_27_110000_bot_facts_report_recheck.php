<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F114 (owner, 27 Nov): fixes from the QA re-check, as cooks and customers are told them. */
return new class extends Migration
{
    private const FACTS = [
        ['cook', 'কমেন্টের খবর', 'কেউ আপনার পোস্টে কমেন্ট করলে (খাবার শেষ হয়ে গেলেও) অ্যাপের 🔔-তে আর ফোনের নোটিফিকেশনে খবর আসে। চাপলে সোজা কমেন্টে যাবে, সেখান থেকেই উত্তর দিন।'],
        ['customer', 'কমেন্টের উত্তর', 'কোনো পোস্টে প্রশ্ন করলে গৃহিণী উত্তর দিলেই অ্যাপের 🔔-তে আর ফোনের নোটিফিকেশনে খবর আসে।'],
        ['customer', 'নিয়মিত গ্রাহকের লগইন', 'নিয়মিত গ্রাহক অ্যাপে ঢুকলে সোজা "প্রতিদিন ঘরের খাবার" পাতা খোলে — সেখানে আজকের বক্স, তার অবস্থা আর ডেলিভারি কোড থাকে।'],
    ];

    public function up(): void
    {
        $now = now();
        $sort = (int) DB::table('bot_facts')->max('sort_order');
        foreach (self::FACTS as [$audience, $topic, $body]) {
            DB::table('bot_facts')->insert([
                'audience' => $audience, 'feature' => 'F114', 'topic' => $topic, 'body' => $body,
                'is_active' => true, 'sort_order' => $sort += 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->whereIn('topic', array_column(self::FACTS, 1))->delete();
        cache()->forget('bot-facts:prompt');
    }
};
