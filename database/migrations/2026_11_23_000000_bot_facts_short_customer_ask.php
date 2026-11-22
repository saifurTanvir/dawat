<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F100 (owner, 22 Nov 23:47): customers get one short question at a time and are never sent to the
 * website; prices stay regular (no offer in the bot). Deliveries start only once Badda has enough customers.
 */
return new class extends Migration
{
    private const BODIES = [
        'খাবার কেনা' => 'বাড্ডায় এখন গ্রাহকের তালিকা তৈরি হচ্ছে; যথেষ্ট গ্রাহক হলে ডেলিভারি শুরু হবে — কাল বা কোনো নির্দিষ্ট দিন থেকে খাবার পাবেন, এমন কথা বলবেন না। গ্রাহককে ওয়েবসাইটে পাঠাবেন না। আগ্রহী হলে আগে শুধু অফিস বা বাসার ঠিকানা, তারপর মোবাইল নম্বর নেবেন; মালিক নিজে ফোন করে কথা বলবেন। টাকা খাবার পাওয়ার সময় নগদ।',
        'নিয়মিত গ্রাহক হতে চাইলে' => 'বাড্ডার পাশের বাসার গৃহিণীর ঘরের রান্না, দুপুরে বাসা বা অফিসে পৌঁছে যায়। একবারে একটাই প্রশ্ন: প্রথমে শুধু অফিস বা বাসার ঠিকানা, ঠিকানা পেলে তারপর মোবাইল নম্বর। নাম (ফেসবুকের নামই যথেষ্ট), কোন দিন বা কোন বেলা — নিজে থেকে না বললে জিজ্ঞেস করবেন না; মালিক ফোনে জেনে নেবেন। কোনো অফার বা ছাড়ের কথা বলবেন না — দাম স্বাভাবিক।',
    ];

    public function up(): void
    {
        foreach (self::BODIES as $topic => $body) {
            DB::table('bot_facts')->where('audience', 'customer')->where('topic', $topic)
                ->update(['body' => $body, 'updated_at' => now()]);
        }
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        cache()->forget('bot-facts:prompt');
    }
};
