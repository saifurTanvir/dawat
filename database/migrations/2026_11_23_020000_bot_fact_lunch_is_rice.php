<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F102 (owner, 23 Nov 08:37): a customer's lunch is home rice and curry — never roti or fried rice. */
return new class extends Migration
{
    private const TOPIC = 'খাবারের দাম (গ্রাহকের জন্য)';

    private const BODY = 'দুপুরের খাবার মানে ঘরের ভাত-তরকারি: ভাতের সাথে মাছ, মুরগি, ডাল, শাক-সবজি বা ভর্তা। দাম সাধারণত ১১০ থেকে ১৬০ টাকা (যেমন ভাত, মাছ ভাজি, ফুলকপির তরকারি ১৩০ টাকা) — দাম গৃহিণী ঠিক করেন, তাই একটু কম-বেশি হতে পারে। দুপুরের খাবার হিসেবে রুটি, ফ্রাইড রাইস বা নাস্তার কথা বলবেন না। টাকা খাবার পাওয়ার সময় নগদ। কোনো ভর্তি ফি নেই।';

    public function up(): void
    {
        DB::table('bot_facts')->where('audience', 'customer')->where('topic', self::TOPIC)->update(['body' => self::BODY, 'updated_at' => now()]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        cache()->forget('bot-facts:prompt');
    }
};
