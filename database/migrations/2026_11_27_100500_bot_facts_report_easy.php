<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F112 (owner, 27 Nov): what changed for cooks and customers after the QA report's easy fixes. */
return new class extends Migration
{
    private const FACTS = [
        ['cook', 'রান্নাঘর খোলার সময় নিজের নাম', 'রান্নাঘর খোলার ফর্মে রান্নাঘরের নামের সাথে নিজের নামও দিতে হয়। দাওয়াত টিম ফোনে এই নামে ডাকবে; ক্রেতারা দেখবেন রান্নাঘরের নাম।'],
        ['cook', 'পোস্টের পর হোয়াটসঅ্যাপে শেয়ার', 'পোস্ট দেওয়ার পর "WhatsApp-এ পরিচিতদের পাঠান" বোতাম আসে — চাপলে আজকের রান্নার নাম আর লিংকসহ একটা মেসেজ তৈরি হয়, পরিচিতদের পাঠাতে পারেন।'],
        ['customer', 'ডেলিভারি কোড অ্যাপে', 'নিয়মিত খাবারের ডেলিভারি কোড এখন SMS-এর পাশাপাশি অ্যাপের "প্রতিদিন ঘরের খাবার" পাতায় আজকের অর্ডারের পাশে দেখা যায়। বক্স হাতে পেলে এই কোডটা এলাকা ম্যানেজারকে বলবেন।'],
        ['customer', 'খাবারের পর তারা দেওয়া', 'খাবার পৌঁছানোর পর অ্যাপে "কেমন লাগল? তারা দিন" দেখা যায় — তারা দিয়ে জানালে আমরা রান্নার মান ঠিক রাখতে পারি।'],
    ];

    public function up(): void
    {
        $now = now();
        $sort = (int) DB::table('bot_facts')->max('sort_order');
        foreach (self::FACTS as [$audience, $topic, $body]) {
            DB::table('bot_facts')->insert([
                'audience' => $audience, 'feature' => 'F112', 'topic' => $topic, 'body' => $body,
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
