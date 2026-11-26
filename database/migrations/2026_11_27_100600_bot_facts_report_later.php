<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F113 (owner, 27 Nov): the QA report's later fixes, as cooks and customers are told them. */
return new class extends Migration
{
    private const FACTS = [
        ['cook', 'অনুমোদনের অপেক্ষায় যা করবেন', 'নিবন্ধনের পর অনুমোদন পর্যন্ত অ্যাপের রান্নাঘর পাতায় দিনে কত প্লেট দিতে পারবেন (১ বা ২) জানিয়ে রাখতে পারেন, আর "কখন ফোন করা যাবে" ঠিক করে রাখুন — দাওয়াত টিম সেই সময়ে ফোন করবে। প্লেটের সংখ্যা টিম ফোনে চূড়ান্ত করে।'],
        ['customer', 'খাবার শেষ হলেও কমেন্ট', 'কোনো পোস্টের খাবার শেষ হয়ে গেলেও পোস্টের পর এক দিন পর্যন্ত কমেন্টে গৃহিণীকে জিজ্ঞেস করা যায় — যেমন পরের বার কবে রাঁধবেন।'],
        ['customer', 'টিম যোগ করলে স্বাগতম SMS', 'দাওয়াত টিম কাউকে নিয়মিত গ্রাহক হিসেবে যোগ করলে তিনি একটা স্বাগতম SMS পান — খাবারের খবর আর ডেলিভারি কোড SMS-এ আসবে, আর dawat.cloud-এ সেই নম্বর দিয়ে ঢুকে অ্যাপে দেখা যাবে।'],
        ['customer', 'একবারের অর্ডারের ডেলিভারি কোড', 'ফিড থেকে একবারের অর্ডার বাসায় পৌঁছানোর হলে ডেলিভারি কোড অ্যাপের "আমার খাবার" পাতায়ও দেখা যায়। খাবার হাতে পেলে কোডটা এলাকা ম্যানেজারকে বলবেন।'],
    ];

    public function up(): void
    {
        $now = now();
        $sort = (int) DB::table('bot_facts')->max('sort_order');
        foreach (self::FACTS as [$audience, $topic, $body]) {
            DB::table('bot_facts')->insert([
                'audience' => $audience, 'feature' => 'F113', 'topic' => $topic, 'body' => $body,
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
