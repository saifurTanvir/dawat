<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F105 (owner, 23 Nov 12:35): ask a would-be customer for their mobile number first, then the address. */
return new class extends Migration
{
    private const BODIES = [
        'খাবার কেনা' => 'বাড্ডায় এখন গ্রাহকের তালিকা তৈরি হচ্ছে; যথেষ্ট গ্রাহক হলে ডেলিভারি শুরু হবে — কাল বা কোনো নির্দিষ্ট দিন থেকে খাবার পাবেন, এমন কথা বলবেন না। গ্রাহককে ওয়েবসাইটে পাঠাবেন না। আগ্রহী হলে আগে মোবাইল নম্বর নেবেন (মালিক নিজে ফোন করে কথা বলবেন), তারপর অফিস বা বাসার ঠিকানা। টাকা খাবার পাওয়ার সময় নগদ।',
        'নিয়মিত গ্রাহক হতে চাইলে' => 'বাড্ডার পাশের বাসার গৃহিণীর ঘরের রান্না, দুপুরে বাসা বা অফিসে পৌঁছে যায়। একবারে একটাই প্রশ্ন: প্রথমে মোবাইল নম্বর, নম্বর পেলে তারপর অফিস বা বাসার ঠিকানা। নাম (ফেসবুকের নামই যথেষ্ট), কোন দিন বা কোন বেলা — নিজে থেকে না বললে জিজ্ঞেস করবেন না; মালিক ফোনে জেনে নেবেন। কোনো অফার বা ছাড়ের কথা বলবেন না — দাম স্বাভাবিক।',
    ];

    public function up(): void
    {
        foreach (self::BODIES as $topic => $body) {
            DB::table('bot_facts')->where('audience', 'customer')->where('topic', $topic)->update(['body' => $body, 'updated_at' => now()]);
        }
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        cache()->forget('bot-facts:prompt');
    }
};
