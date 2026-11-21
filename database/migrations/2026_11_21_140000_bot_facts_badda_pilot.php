<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner, 21 Nov: the test operation starts in Badda; ads for customers and shahidars there. */
return new class extends Migration
{
    private const TOPICS = ['বাড্ডায় প্রথম শুরু', 'নিয়মিত গ্রাহক হতে চাইলে', 'খাবারের দাম (গ্রাহকের জন্য)', 'শাহিদার (ডেলিভারি) হতে চাইলে'];

    public function up(): void
    {
        $now = now();
        $sort = (int) DB::table('bot_facts')->max('sort_order');
        foreach ([
            ['all', 'বাড্ডায় প্রথম শুরু', 'দাওয়াত প্রথমে বাড্ডায় (উত্তর, মধ্য, দক্ষিণ, মেরুল বাড্ডা আর শাহজাদপুর) চালু হচ্ছে। বাড্ডার গৃহিণী আর গ্রাহকদের দিয়ে শুরু; অন্য এলাকায় পরে, ধাপে ধাপে। অন্য এলাকার কেউ লিখলে বলবেন: আপনার এলাকায় চালু হলে জানানো হবে — তবু যোগ দিয়ে রাখতে পারেন।'],
            ['customer', 'নিয়মিত গ্রাহক হতে চাইলে', 'বাড্ডার পাশের বাসার গৃহিণীর ঘরের রান্না, দুপুরে বাসা বা অফিসে পৌঁছে যায়। গ্রাহক হতে চাইলে নাম, মোবাইল নম্বর, পুরো ঠিকানা (এলাকা, রোড, বাসা) আর কোন দিন/বেলায় খাবেন জেনে নেবেন; টিম যোগ করে কনফার্ম করবে। প্রথম দিন থেকে প্রতিদিন সকালে জানানো হবে আজ কী রান্না, না খেতে চাইলে সেদিন বাদ দেওয়া যায়।'],
            ['customer', 'খাবারের দাম (গ্রাহকের জন্য)', 'এক জনের পুরো দুপুরের খাবার (ভাত, তরকারি, ডাল) সাধারণত ১০০ টাকার মতো — দাম গৃহিণী ঠিক করেন, তাই একটু কম-বেশি হতে পারে। টাকা খাবার পাওয়ার সময় নগদ। কোনো ভর্তি ফি নেই।'],
            ['shahidar', 'শাহিদার (ডেলিভারি) হতে চাইলে', 'বাড্ডায় দুপুরে গৃহিণীর বাসা থেকে খাবারের বক্স নিয়ে কাছের গ্রাহককে পৌঁছে দেওয়ার কাজ (প্রতি বক্সে আয়, সাইকেল বা হেঁটে)। আবেদন করতে এই লিংকে ফর্মটা পূরণ করতে বলবেন: dawat.cloud/join/shahidar — টিম দেখে ফোন করবে।'],
        ] as [$audience, $topic, $body]) {
            DB::table('bot_facts')->insert([
                'audience' => $audience, 'feature' => null, 'topic' => $topic, 'body' => $body,
                'is_active' => true, 'sort_order' => $sort += 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->whereIn('topic', self::TOPICS)->delete();
        cache()->forget('bot-facts:prompt');
    }
};
