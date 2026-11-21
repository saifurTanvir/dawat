<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner, 21 Nov: Dawat takes only cooked food — no raw or cut vegetables / ready-to-cook items. */
return new class extends Migration
{
    private const TOPIC = 'শুধু রান্না করা খাবার';

    public function up(): void
    {
        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'cook',
            'feature' => null,
            'topic' => self::TOPIC,
            'body' => 'দাওয়াতে শুধু রান্না করা খাবার (আর পিঠা-আচারের মতো ঘরে বানানো খাবার) বিক্রি হয়। কাঁচা বা কাটা সবজি, পেঁয়াজ-রসুন-মরিচ কাটা, "রেডি টু কুক" জিনিস — এসব দাওয়াত নেয় না। কেউ জিজ্ঞেস করলে ভদ্রভাবে না বলবেন, আর ঘরের রান্না করা খাবার দিতে উৎসাহ দেবেন।',
            'is_active' => true,
            'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('topic', self::TOPIC)->delete();
        cache()->forget('bot-facts:prompt');
    }
};
